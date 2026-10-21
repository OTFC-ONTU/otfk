<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Пошук «осиротілих» файлів публічного диска (storage/app/public): вилучення PDF/фото
 * з тексту сторінки чи очищення поля файлу прибирає лише посилання, сам файл лишається
 * доступним за прямою адресою /storage/…. Команда шукає файли, на які не посилається
 * жоден рядок БД, і без --apply лише звітує.
 *
 * Посиланням вважається будь-яке входження відносного шляху файлу (напр.
 * documents/vbudovani/x.pdf) у будь-якому текстовому стовпці будь-якої таблиці, крім
 * службових (кеш, сесії, черги, журнали відвідувань/404): так враховуються HTML
 * редактора (/storage/…, зокрема URL-кодовані й з HTML-сутностями), поля файлів (у т.ч.
 * JSON із \/ та \uXXXX), налаштування (логотип), редиректи й file_mirrors. Хибний збіг
 * лише зберігає файл — команда помиляється в бік «не чіпати».
 *
 * Файли старого сайту (mirror/, imported/, news/imported/, documents/…/<md5>.pdf) типово
 * не перевіряються: otfk:legacy-map будує з них карту старих адрес, навіть якщо в тексті
 * на них уже немає посилань (--include-legacy — перевіряти й їх).
 *
 * --apply не видаляє, а переносить файли в storage/app/private/orphan-files/<мітка>/files/
 * (поза вебом); manifest.json із запланованим списком пишеться ДО перенесення, а
 * --restore=<мітка> повертає все, що лежить у files/, тож обірваний запуск теж
 * відновлюється. Файли, змінені пізніше за --older-than днів, пропускаються.
 */
class OrphanFiles extends Command
{
    protected $signature = 'otfk:orphan-files
        {--apply : Перенести знайдені файли в кошик storage/app/private/orphan-files (без опції — лише звіт)}
        {--path= : Перевіряти лише цей каталог диска, напр. documents/vbudovani}
        {--older-than=1 : Пропускати файли, змінені менше ніж N днів тому}
        {--limit=200 : Скільки файлів показати у звіті (0 — усі)}
        {--include-legacy : Перевіряти й файли старого сайту (mirror, imported, news/imported, md5-PDF документів)}
        {--find= : Показати, які таблиці та стовпці посилаються на цей файл (шлях на диску)}
        {--restore= : Повернути файли з кошика за назвою його каталогу}
        {--trash-dir= : Каталог кошика (типово storage/app/private/orphan-files)}';

    protected $description = 'Звіт/перенесення в кошик файлів storage/app/public, на які не посилається жоден запис БД';

    public const TRASH_DIR = 'app/private/orphan-files';

    /** Службові таблиці: їхній вміст — не посилання на файли сайту. */
    private const SKIP_TABLES = [
        'cache', 'cache_locks', 'sessions', 'jobs', 'failed_jobs', 'job_batches',
        'migrations', 'password_reset_tokens', 'not_found_logs', 'site_visits',
    ];

    /** Тимчасові/службові каталоги диска. */
    private const SKIP_DIRS = ['livewire-tmp/'];

    /** Файли старого сайту, з яких otfk:legacy-map будує карту старих адрес. */
    private const LEGACY_DIRS = ['mirror/', 'imported/', 'news/imported/'];

    private const LEGACY_PATTERN = '~^documents/[^/]+/[0-9a-f]{32}\.pdf$~';

    /** Оригінали, для яких ImageOptimizer створює сусідню .webp-копію. */
    private const WEBP_SOURCES = ['jpg', 'jpeg', 'png'];

    public function handle(): int
    {
        if ($this->option('restore')) {
            return $this->restore((string) $this->option('restore'));
        }
        if ($this->option('find')) {
            return $this->find(trim((string) $this->option('find'), '/'));
        }

        $disk = Storage::disk('public');
        $prefix = trim((string) $this->option('path'), '/');
        if ($prefix !== '' && ! $disk->directoryExists($prefix)) {
            $this->error("Каталогу {$prefix} немає на диску public.");

            return self::FAILURE;
        }

        $legacy = (bool) $this->option('include-legacy');
        $files = array_values(array_filter(
            $prefix === '' ? $disk->allFiles() : $disk->allFiles($prefix),
            fn (string $path) => ! str_starts_with(basename($path), '.')
                && ! $this->startsWithAny($path, self::SKIP_DIRS)
                && ($legacy || ! $this->isLegacy($path)),
        ));

        $referenced = $this->referencedFiles($files, $this->referenceHaystack());

        $usedImages = [];
        foreach ($files as $path) {
            $ext = pathinfo($path, PATHINFO_EXTENSION);
            if (isset($referenced[$path]) && in_array(strtolower($ext), self::WEBP_SOURCES, true)) {
                $usedImages[substr($path, 0, -strlen($ext) - 1)] = true;
            }
        }

        $cutoff = now()->subDays(max(0, (int) $this->option('older-than')))->getTimestamp();
        $orphans = [];
        $recent = 0;
        foreach ($files as $path) {
            // .webp-копія ImageOptimizer живе, доки використовується її оригінал (будь-який регістр розширення).
            if (isset($referenced[$path]) || (str_ends_with(strtolower($path), '.webp') && isset($usedImages[substr($path, 0, -5)]))) {
                continue;
            }
            if ($disk->lastModified($path) > $cutoff) {
                $recent++;

                continue;
            }
            $orphans[$path] = $disk->size($path);
        }

        $this->report($orphans, count($files), $recent, $legacy);

        if (! $this->option('apply') || $orphans === []) {
            return self::SUCCESS;
        }

        return $this->moveToTrash($orphans);
    }

    /**
     * Точна перевірка «шлях входить у haystack» без str_contains для кожного файлу: шлях
     * закінчується на «.розширення», тож будь-яке його входження закінчується там, де в
     * haystack закінчується це розширення. Для кожного розширення знаходимо ці позиції один раз
     * і порівнюємо хвости потрібної довжини через хеш-множину.
     *
     * @param  list<string>  $files
     * @return array<string, true>
     */
    private function referencedFiles(array $files, string $haystack): array
    {
        $groups = [];
        $referenced = [];
        foreach ($files as $path) {
            $ext = pathinfo($path, PATHINFO_EXTENSION);
            if ($ext === '' || str_contains($ext, '/')) {
                if (str_contains($haystack, $path)) {
                    $referenced[$path] = true;
                }

                continue;
            }
            $groups['.'.$ext][strlen($path)][] = $path;
        }

        foreach ($groups as $suffix => $byLength) {
            $ends = [];
            $offset = 0;
            while (($pos = strpos($haystack, $suffix, $offset)) !== false) {
                $ends[] = $pos + strlen($suffix);
                $offset = $pos + 1;
            }
            if ($ends === []) {
                continue;
            }
            foreach ($byLength as $length => $paths) {
                $tails = [];
                foreach ($ends as $end) {
                    if ($end >= $length) {
                        $tails[substr($haystack, $end - $length, $length)] = true;
                    }
                }
                foreach ($paths as $path) {
                    if (isset($tails[$path])) {
                        $referenced[$path] = true;
                    }
                }
            }
        }

        return $referenced;
    }

    /**
     * Усі текстові значення БД одним рядком, нормалізовані так, щоб шлях файлу шукався
     * простим входженням: HTML-сутності, екранування JSON (\/ і \uXXXX), URL-кодування.
     */
    private function referenceHaystack(): string
    {
        $parts = [];
        $this->eachTextValue(function (string $table, string $column, string $value) use (&$parts): void {
            $parts[] = $value;
        });
        $haystack = implode("\n", $parts);
        unset($parts);

        return $haystack;
    }

    /** @param  callable(string, string, string): void  $callback  таблиця, стовпець, нормалізоване значення */
    private function eachTextValue(callable $callback): void
    {
        // Лише поточна БД: без схеми MySQL повертає таблиці всіх доступних баз.
        foreach (Schema::getTables(Schema::getCurrentSchemaName()) as $table) {
            $name = $table['name'];
            if (in_array($name, self::SKIP_TABLES, true)) {
                continue;
            }
            $columns = array_values(array_map(
                fn (array $column) => $column['name'],
                array_filter(Schema::getColumns($name), fn (array $column) => preg_match('/char|text|json|clob|string/i', (string) $column['type_name']) === 1),
            ));
            if ($columns === []) {
                continue;
            }
            foreach (DB::table($name)->select($columns)->cursor() as $row) {
                foreach ((array) $row as $column => $value) {
                    if (is_string($value) && $value !== '' && str_contains($value, '.')) {
                        $callback($name, $column, $this->normalize($value));
                    }
                }
            }
        }
    }

    private function normalize(string $value): string
    {
        $value = str_replace('\\/', '/', $value);
        if (str_contains($value, '\\u')) {
            $value = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', fn (array $m) => mb_chr(hexdec($m[1]), 'UTF-8') ?: $m[0], $value) ?? $value;
        }
        if (str_contains($value, '&')) {
            $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        if (str_contains($value, '%')) {
            // Обидва варіанти: декодований (кирилиця, пробіли) і як є (буквальний «%» в імені).
            $value .= "\n".rawurldecode($value);
        }

        return $value;
    }

    private function isLegacy(string $path): bool
    {
        return $this->startsWithAny($path, self::LEGACY_DIRS) || preg_match(self::LEGACY_PATTERN, $path) === 1;
    }

    /** Де саме згадується файл — щоб прибрати всі посилання перед видаленням (напр. персональні дані). */
    private function find(string $path): int
    {
        if (! Storage::disk('public')->exists($path)) {
            $this->warn("Файлу {$path} немає на диску public — шукаю лише згадки в БД.");
        }

        $hits = [];
        $this->eachTextValue(function (string $table, string $column, string $value) use ($path, &$hits): void {
            if (str_contains($value, $path)) {
                $hits["{$table}.{$column}"] = ($hits["{$table}.{$column}"] ?? 0) + 1;
            }
        });

        if ($hits === []) {
            $this->info("Посилань на {$path} у БД немає.");

            return self::SUCCESS;
        }
        foreach ($hits as $place => $count) {
            $this->line("  {$place}: {$count}");
        }
        $this->info('Файл використовується: приберіть ці посилання (зокрема англійську версію, чернетки, редиректи), тоді він стане «сиротою».');

        return self::SUCCESS;
    }

    /** @param  array<string, int>  $orphans */
    private function report(array $orphans, int $total, int $recent, bool $legacy): void
    {
        $limit = max(0, (int) $this->option('limit'));
        $shown = 0;
        foreach ($orphans as $path => $size) {
            if ($limit > 0 && $shown >= $limit) {
                $this->line(sprintf('  … і ще %d (див. --limit=0)', count($orphans) - $shown));
                break;
            }
            $this->line(sprintf('  %s  (%s)', $path, $this->humanSize($size)));
            $shown++;
        }

        $byDir = [];
        foreach ($orphans as $path => $size) {
            $dir = str_contains($path, '/') ? strstr($path, '/', true) : '.';
            $byDir[$dir] = ($byDir[$dir] ?? 0) + 1;
        }
        ksort($byDir);
        foreach ($byDir as $dir => $count) {
            $this->line("  {$dir}/: {$count}");
        }

        $this->info(sprintf(
            'Перевірено файлів: %d; без посилань: %d (%s); пропущено нових (молодші за --older-than): %d.',
            $total, count($orphans), $this->humanSize(array_sum($orphans)), $recent,
        ));
        if (! $legacy) {
            $this->comment('Файли старого сайту (mirror/, imported/, news/imported/, md5-PDF документів) не перевірялись — див. --include-legacy.');
        }
        if (! $this->option('apply') && $orphans !== []) {
            $this->comment('Це лише звіт. Щоб перенести файли в кошик (з можливістю повернення), додайте --apply.');
        }
    }

    private function trashRoot(): string
    {
        return rtrim((string) ($this->option('trash-dir') ?: storage_path(self::TRASH_DIR)), '/\\');
    }

    /** @param  array<string, int>  $orphans */
    private function moveToTrash(array $orphans): int
    {
        $disk = Storage::disk('public');
        $batch = now()->format('Ymd-His').'-'.bin2hex(random_bytes(3));
        $dir = $this->trashRoot().'/'.$batch;
        File::ensureDirectoryExists($dir.'/files');

        // Маніфест — до перенесення: обірваний запуск лишає зрозумілий кошик, який --restore поверне.
        File::put($dir.'/manifest.json', json_encode([
            'created_at' => now()->toIso8601String(),
            'files' => array_keys($orphans),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

        $moved = 0;
        $failed = 0;
        foreach (array_keys($orphans) as $path) {
            $target = $dir.'/files/'.$path;
            File::ensureDirectoryExists(dirname($target));
            if (@rename($disk->path($path), $target)) {
                $moved++;
            } else {
                $failed++;
                $this->warn("Не вдалося перенести {$path}");
            }
        }

        $this->info(sprintf('Перенесено в кошик %s: %d файлів. Повернути: php artisan otfk:orphan-files --restore=%s', $dir, $moved, $batch));
        $this->comment('Перевірте сайт і журнал 404 (звернення до /storage/…); коли все гаразд, каталог кошика можна видалити вручну.');

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function restore(string $batch): int
    {
        if (! preg_match('/^[0-9A-Za-z_-]+$/', $batch)) {
            $this->error('Некоректна назва каталогу кошика.');

            return self::FAILURE;
        }
        $dir = $this->trashRoot().'/'.$batch.'/files';
        if (! is_dir($dir)) {
            $this->error("Кошика {$batch} немає.");

            return self::FAILURE;
        }

        // Повертаємо те, що фактично лежить у кошику (маніфест може бути неповним після збою).
        $disk = Storage::disk('public');
        $restored = 0;
        $skipped = 0;
        foreach (File::allFiles($dir, true) as $file) {
            $path = str_replace('\\', '/', $file->getRelativePathname());
            if ($disk->exists($path)) {
                $this->warn("Пропущено {$path}: на диску вже є файл з цим іменем.");
                $skipped++;

                continue;
            }
            File::ensureDirectoryExists(dirname($disk->path($path)));
            if (@rename($file->getPathname(), $disk->path($path))) {
                $restored++;
            } else {
                $this->warn("Не вдалося повернути {$path}");
                $skipped++;
            }
        }

        $this->info("Повернуто файлів: {$restored}; пропущено: {$skipped}.");

        return $skipped > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function startsWithAny(string $path, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function humanSize(int $bytes): string
    {
        return match (true) {
            $bytes >= 1048576 => sprintf('%.1f МБ', $bytes / 1048576),
            $bytes >= 1024 => sprintf('%.0f КБ', $bytes / 1024),
            default => $bytes.' Б',
        };
    }
}
