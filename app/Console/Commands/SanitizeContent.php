<?php

namespace App\Console\Commands;

use App\Models\Department;
use App\Models\News;
use App\Models\Page;
use App\Models\Specialty;
use App\Models\Staff;
use App\Support\HtmlSanitizer;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Masterminds\HTML5;

/**
 * Інвентаризація та контрольоване очищення вже збереженого HTML: каст SafeHtml
 * діє лише під час запису, тому матеріали, збережені до його появи, читаються
 * як є. Без --apply команда лише звітує, які записи й поля зміняться.
 *
 * Порядок при --apply: спочатку повний прохід зі збором змін, потім резервна
 * копія оригіналів у storage/app/private (JSON; невдалий запис зупиняє
 * команду до будь-якої зміни БД), і лише тоді запис атомарним умовним UPDATE
 * через Query Builder (без NewsObserver/Telegram, без зміни updated_at):
 * у WHERE — усі відскановані значення, тож правка редактора після сканування
 * ніколи не затирається (0 змінених рядків = пропуск, код виходу 1). Якщо
 * переклад був актуальним (translation_source_hash збігався з поточним
 * хешем), хеш перераховується, щоб очищення не позначило його застарілим.
 *
 * Класифікація «видалено / лише нормалізація» — за DOM-інвентарем елементів
 * і атрибутів (той самий парсер HTML5, що й у санітайзері), тож
 * `<img/src=x onerror=…>` рахується як видалений onerror, а не як
 * нормалізація.
 */
class SanitizeContent extends Command
{
    protected $signature = 'otfk:sanitize-content
        {--apply : Записати очищений HTML (без опції — лише звіт)}
        {--connection= : Інше зʼєднання БД, напр. hosting (лише для звіту або з явним --apply)}
        {--show : Показати diff-фрагменти для кожного зміненого поля}
        {--backup-dir= : Каталог резервних копій (типово storage/app/private)}';

    protected $description = 'Звіт/очищення збереженого HTML редактора санітайзером SafeHtml (сторінки, новини, спеціальності, підрозділи, персонал)';

    /** Гачок для тестів: виконується між скануванням і резервною копією (імітує паралельну правку). */
    public static ?\Closure $afterScan = null;

    /** Гачок для тестів: виконується між перечитуванням запису й умовним UPDATE (найвужче вікно гонки). */
    public static ?\Closure $beforeWrite = null;

    /** @var array<class-string<Model>, list<string>> */
    private const FIELDS = [
        Page::class => ['body', 'body_en'],
        News::class => ['body', 'body_en'],
        Specialty::class => ['description', 'description_en'],
        Department::class => ['description', 'description_en'],
        Staff::class => ['bio', 'bio_en'],
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $connection = $this->option('connection') ?: null;

        // Фаза 1: лише читання — збір усіх змін.
        $changes = [];
        $scanned = 0;
        $dangerous = 0;

        foreach (self::FIELDS as $model => $fields) {
            $query = $connection ? $model::on($connection) : $model::query();

            $query->orderBy('id')->chunkById(100, function ($records) use ($model, $fields, &$changes, &$scanned, &$dangerous) {
                foreach ($records as $record) {
                    $scanned++;
                    $diff = [];

                    foreach ($fields as $field) {
                        $raw = $record->getRawOriginal($field);
                        if ($raw === null || $raw === '') {
                            continue;
                        }
                        $clean = HtmlSanitizer::clean((string) $raw);
                        if ($clean !== $raw) {
                            $diff[$field] = ['before' => $raw, 'after' => $clean, 'removed' => $this->removedMarkup($raw, $clean)];
                        }
                    }

                    if ($diff === []) {
                        continue;
                    }

                    $substantive = array_filter(array_map(fn ($pair) => $pair['removed'], $diff));
                    if ($substantive !== []) {
                        $dangerous++;
                    }

                    $label = class_basename($model).' #'.$record->getKey().' ('.($record->slug ?? $record->full_name ?? $record->title ?? '').')';
                    $this->line($label.': '.implode(', ', array_keys($diff))
                        .($substantive !== []
                            ? '  ← видалено: '.implode('; ', array_map(fn ($f, $r) => $f.' ['.implode(' ', $r).']', array_keys($substantive), $substantive))
                            : '  (лише нормалізація розмітки)'));

                    if ($this->option('show')) {
                        foreach ($diff as $field => $pair) {
                            $this->comment("  {$field}: -".mb_strlen($pair['before']).' / +'.mb_strlen($pair['after']).' символів');
                            $this->line('    '.mb_substr($this->firstDifference($pair['before'], $pair['after']), 0, 300));
                        }
                    }

                    $changes[] = [
                        'model' => $model,
                        'id' => $record->getKey(),
                        'fields' => array_map(fn ($pair) => $pair['after'], $diff),
                        'before' => array_map(fn ($pair) => $pair['before'], $diff),
                        'translation_source_hash' => $record->getRawOriginal('translation_source_hash'),
                        'updated_at' => $record->getRawOriginal('updated_at'),
                    ];
                }
            });
        }

        $this->info(sprintf('Звіт: переглянуто %d записів, потребують очищення %d, з них із видаленими тегами/атрибутами — %d (решта — лише нормалізація розмітки).',
            $scanned, count($changes), $dangerous));

        if (! $apply || $changes === []) {
            return self::SUCCESS;
        }

        if (self::$afterScan !== null) {
            (self::$afterScan)(); // лише для тестів: імітація правки редактора між скануванням і записом
        }

        // Фаза 2: резервна копія ДО будь-якої зміни; невдача = зупинка. Ім'я унікальне
        // (мікросекунди + випадковий суфікс), наявний файл ніколи не перезаписується.
        $dir = rtrim((string) ($this->option('backup-dir') ?: storage_path('app/private')), '/\\');
        $path = $dir.'/sanitize-backup-'.now()->format('Ymd-His-u').'-'.bin2hex(random_bytes(3)).'.json';
        if (! is_dir(dirname($path)) && ! @mkdir(dirname($path), 0775, true)) {
            $this->error("Не вдалося створити каталог резервної копії: {$path}");

            return self::FAILURE;
        }
        if (file_exists($path)) {
            $this->error("Файл резервної копії вже існує ({$path}) — БД не змінено.");

            return self::FAILURE;
        }
        $backup = json_encode([
            'created_at' => now()->toIso8601String(),
            'connection' => $connection ?? config('database.default'),
            'database' => ($connection ? DB::connection($connection) : DB::connection())->getDatabaseName(),
            'records' => array_map(fn ($c) => [
                'model' => $c['model'],
                'id' => $c['id'],
                'fields' => $c['before'],
                'translation_source_hash' => $c['translation_source_hash'],
                'updated_at' => $c['updated_at'],
            ], $changes),
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($backup === false || file_put_contents($path, $backup, LOCK_EX) !== strlen($backup) || json_decode((string) file_get_contents($path)) === null) {
            $this->error("Резервну копію не записано ({$path}) — БД не змінено.");

            return self::FAILURE;
        }
        $this->info("Резервна копія оригіналів: {$path}");

        // Фаза 3: запис одним умовним UPDATE: у WHERE входять усі відскановані
        // значення (очищувані поля — побайтово, updated_at, translation_source_hash).
        // Якщо редактор змінив запис після сканування — умова не збігається,
        // UPDATE зачіпає 0 рядків, запис пропускається (код виходу 1, повторити
        // команду). Вікна між перевіркою й записом немає: перевірка і є запис.
        // Новий хеш перекладу обчислюється за актуальним станом; якщо він змінився
        // між перечитуванням і UPDATE, умова на updated_at/хеш це також відхилить.
        $written = 0;
        $skipped = 0;
        foreach ($changes as $change) {
            $model = $change['model'];
            $record = ($connection ? $model::on($connection) : $model::query())->find($change['id']);
            if ($record === null) {
                $skipped++;
                $this->warn(class_basename($model).' #'.$change['id'].': запис зник після сканування — пропущено.');

                continue;
            }

            $values = $change['fields'];
            if (method_exists($record, 'currentTranslationSourceHash')
                && filled($change['translation_source_hash'])
                && $change['translation_source_hash'] === $record->currentTranslationSourceHash()) {
                $record->setRawAttributes($change['fields'] + $record->getAttributes());
                $values['translation_source_hash'] = $record->currentTranslationSourceHash();
            }

            if (self::$beforeWrite !== null) {
                (self::$beforeWrite)(); // лише для тестів: правка редактора між перечитуванням і UPDATE
            }

            $db = DB::connection($connection);
            $binary = $db->getDriverName() === 'mysql' ? 'BINARY ' : '';
            $query = $db->table($record->getTable())->where($record->getKeyName(), $change['id']);
            foreach (['updated_at' => $change['updated_at'], 'translation_source_hash' => $change['translation_source_hash']] + $change['before'] as $field => $before) {
                $before === null
                    ? $query->whereNull($field)
                    : $query->whereRaw($binary.$field.' = ?', [$before]);
            }

            if ($query->update($values) === 1) {
                $written++;
            } else {
                $skipped++;
                $this->warn(class_basename($model).' #'.$change['id'].': змінено після сканування — пропущено, повторіть команду.');
            }
        }

        $this->info("Очищено: змінено {$written} записів".($skipped ? ", пропущено {$skipped} (змінені після сканування)" : '').'.');

        return $skipped ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Елементи й атрибути, які є в оригіналі, але зникли після очищення
     * (без службових tbody/thead/tfoot, що їх додає парсер). Порожній масив —
     * зміна зводиться до нормалізації (лапки, <img />, кодування сутностей).
     *
     * @return list<string>
     */
    private function removedMarkup(string $before, string $after): array
    {
        $inventory = function (string $html): array {
            $found = [];
            $fragment = (new HTML5)->loadHTMLFragment($html);
            $walk = function (\DOMNode $node) use (&$walk, &$found): void {
                if ($node instanceof \DOMElement) {
                    $name = strtolower($node->tagName);
                    $found['<'.$name.'>'] = ($found['<'.$name.'>'] ?? 0) + 1;
                    foreach ($node->attributes ?? [] as $attr) {
                        $attrName = strtolower($attr->name);
                        $key = '<'.$name.' '.$attrName.'>';
                        $found[$key] = ($found[$key] ?? 0) + 1;

                        // Значення style/class теж інвентаризуються: видалення position:fixed або
                        // класу z-50 при збереженому атрибуті — не нормалізація.
                        if ($attrName === 'style') {
                            foreach (explode(';', $attr->value) as $declaration) {
                                $property = strtolower(trim(strtok($declaration, ':') ?: ''));
                                if ($property !== '' && str_contains($declaration, ':')) {
                                    $found['<'.$name.' style:'.$property.'>'] = ($found['<'.$name.' style:'.$property.'>'] ?? 0) + 1;
                                }
                            }
                        } elseif ($attrName === 'class') {
                            foreach (preg_split('/\s+/u', trim($attr->value)) ?: [] as $class) {
                                if ($class !== '') {
                                    $found['<'.$name.' class:'.$class.'>'] = ($found['<'.$name.' class:'.$class.'>'] ?? 0) + 1;
                                }
                            }
                        }
                    }
                }
                foreach ($node->childNodes ?? [] as $child) {
                    $walk($child);
                }
            };
            $walk($fragment);

            return $found;
        };

        $removed = [];
        $afterCounts = $inventory($after);
        foreach ($inventory($before) as $key => $count) {
            if (in_array($key, ['<tbody>', '<thead>', '<tfoot>'], true)) {
                continue;
            }
            $missing = $count - ($afterCounts[$key] ?? 0);
            if ($missing > 0) {
                $removed[] = $key.($missing > 1 ? '×'.$missing : '');
            }
        }

        return $removed;
    }

    private function firstDifference(string $before, string $after): string
    {
        $i = 0;
        $max = min(strlen($before), strlen($after));
        while ($i < $max && $before[$i] === $after[$i]) {
            $i++;
        }
        $start = max(0, $i - 60);

        return '…'.substr($before, $start, 160).'  ⇒  '.substr($after, $start, 160).'…';
    }
}
