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
 * --apply не видаляє, а переносить файли в storage/app/private/orphan-files/<мітка>/
 * (поза вебом) з manifest.json; --restore=<мітка> повертає їх на місце. Файли, змінені
 * пізніше за --older-than днів, пропускаються (щойно завантажене в незбережену форму).
 */
class OrphanFiles extends Command
{
    protected $signature = 'otfk:orphan-files
        {--apply : Перенести знайдені файли в кошик storage/app/private/orphan-files (без опції — лише звіт)}
        {--path= : Перевіряти лише цей каталог диска, напр. documents/vbudovani}
        {--older-than=1 : Пропускати файли, змінені менше ніж N днів тому}
        {--limit=200 : Скільки файлів показати у звіті (0 — усі)}
        {--restore= : Повернути файли з кошика за назвою його каталогу}';

    protected $description = 'Звіт/перенесення в кошик файлів storage/app/public, на які не посилається жоден запис БД';

    public const TRASH_DIR = 'app/private/orphan-files';

    /** Службові таблиці: їхній вміст — не посилання на файли сайту. */
    private const SKIP_TABLES = [
        'cache', 'cache_locks', 'sessions', 'jobs', 'failed_jobs', 'job_batches',
        'migrations', 'password_reset_tokens', 'not_found_logs', 'site_visits',
    ];

    /** Тимчасові/службові каталоги диска. */
    private const SKIP_DIRS = ['livewire-tmp/'];

    /** Оригінали, для яких ImageOptimizer створює сусідню .webp-копію. */
    private const WEBP_SOURCES = ['jpg', 'jpeg', 'png'];

    public function handle(): int
    {
        if ($this->option('restore')) {
            return $this->restore((string) $this->option('restore'));
        }

        $disk = Storage::disk('public');
        $prefix = trim((string) $this->option('path'), '/');
        if ($prefix !== '' && ! $disk->directoryExists($prefix)) {
            $this->error("Каталогу {$prefix} немає на диску public.");

            return self::FAILURE;
        }

        $cutoff = now()->subDays(max(0, (int) $this->option('older-than')))->getTimestamp();
        $haystack = $this->referenceHaystack();

        $files = array_values(array_filter(
            $prefix === '' ? $disk->allFiles() : $disk->allFiles($prefix),
            fn (string $path) => ! str_starts_with(basename($path), '.')
                && ! $this->startsWithAny($path, self::SKIP_DIRS),
        ));
        $present = array_flip($files);

        $orphans = [];
        $recent = 0;
        foreach ($files as $path) {
            if (str_contains($haystack, $path) || $this->isVariantOfUsed($path, $present, $haystack)) {
                continue;
            }
            if ($disk->lastModified($path) > $cutoff) {
                $recent++;

                continue;
            }
            $orphans[$path] = $disk->size($path);
        }

        $this->report($orphans, count($files), $recent);

        if (! $this->option('apply') || $orphans === []) {
            return self::SUCCESS;
        }

        return $this->moveToTrash($orphans);
    }

    /**
     * Усі текстові значення БД одним рядком, нормалізовані так, щоб шлях файлу шукався
     * простим входженням: HTML-сутності, екранування JSON (\/ і \uXXXX), URL-кодування.
     */
    private function referenceHaystack(): string
    {
        $parts = [];
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
                foreach ((array) $row as $value) {
                    if (is_string($value) && $value !== '' && str_contains($value, '.')) {
                        $parts[] = $this->normalize($value);
                    }
                }
            }
        }

        return implode("\n", $parts);
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

    /** .webp-копія ImageOptimizer живе, доки використовується її оригінал. */
    private function isVariantOfUsed(string $path, array $present, string $haystack): bool
    {
        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'webp') {
            return false;
        }
        $base = substr($path, 0, -strlen('.webp'));
        foreach (self::WEBP_SOURCES as $ext) {
            foreach ([$ext, strtoupper($ext)] as $variant) {
                $source = $base.'.'.$variant;
                if (isset($present[$source]) && str_contains($haystack, $source)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @param  array<string, int>  $orphans */
    private function report(array $orphans, int $total, int $recent): void
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
        if (! $this->option('apply') && $orphans !== []) {
            $this->comment('Це лише звіт. Щоб перенести файли в кошик (з можливістю повернення), додайте --apply.');
        }
    }

    /** @param  array<string, int>  $orphans */
    private function moveToTrash(array $orphans): int
    {
        $disk = Storage::disk('public');
        $batch = now()->format('Ymd-His').'-'.bin2hex(random_bytes(3));
        $dir = storage_path(self::TRASH_DIR.'/'.$batch);
        File::ensureDirectoryExists($dir.'/files');

        $moved = [];
        $failed = 0;
        foreach (array_keys($orphans) as $path) {
            $target = $dir.'/files/'.$path;
            File::ensureDirectoryExists(dirname($target));
            if (@rename($disk->path($path), $target)) {
                $moved[] = $path;
            } else {
                $failed++;
                $this->warn("Не вдалося перенести {$path}");
            }
        }

        File::put($dir.'/manifest.json', json_encode([
            'created_at' => now()->toIso8601String(),
            'files' => $moved,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

        $this->info(sprintf('Перенесено в кошик %s: %d файлів. Повернути: php artisan otfk:orphan-files --restore=%s', 'storage/'.self::TRASH_DIR.'/'.$batch, count($moved), $batch));
        $this->comment('Коли переконаєтеся, що на сайті нічого не зламалось, каталог кошика можна видалити вручну.');

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function restore(string $batch): int
    {
        if (! preg_match('/^[0-9A-Za-z_-]+$/', $batch)) {
            $this->error('Некоректна назва каталогу кошика.');

            return self::FAILURE;
        }
        $dir = storage_path(self::TRASH_DIR.'/'.$batch);
        $manifest = json_decode((string) @file_get_contents($dir.'/manifest.json'), true);
        if (! is_array($manifest) || ! is_array($manifest['files'] ?? null)) {
            $this->error("Кошика {$batch} немає або в ньому відсутній manifest.json.");

            return self::FAILURE;
        }

        $disk = Storage::disk('public');
        $restored = 0;
        $skipped = 0;
        foreach ($manifest['files'] as $path) {
            $source = $dir.'/files/'.$path;
            if (! is_string($path) || str_contains($path, '..') || ! is_file($source)) {
                $skipped++;

                continue;
            }
            if ($disk->exists($path)) {
                $this->warn("Пропущено {$path}: на диску вже є файл з цим іменем.");
                $skipped++;

                continue;
            }
            File::ensureDirectoryExists(dirname($disk->path($path)));
            if (@rename($source, $disk->path($path))) {
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
        return $bytes >= 1048576 ? sprintf('%.1f МБ', $bytes / 1048576) : sprintf('%.0f КБ', $bytes / 1024);
    }
}
