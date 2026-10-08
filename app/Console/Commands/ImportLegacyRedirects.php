<?php

namespace App\Console\Commands;

use App\Models\LegacyRedirect;
use App\Support\LegacyRedirects;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

/**
 * Імпорт карти старих адрес із CSV (docs/seo-plan.md, етап 1).
 *
 * Колонки: source, target, code, note. code — 301 (за замовчуванням для
 * рядка з target), 308 або 410 (без target). Без --apply лише звіт: нові,
 * змінені, без змін і конфлікти (невірні адреси, жива стара адреса, зовнішнє
 * чи відсутнє призначення, дублі, цикли й ланцюжки). Запис ідемпотентний за
 * нормалізованою старою адресою; конфліктні рядки не записуються. З --apply
 * копія CSV і звіт JSON зберігаються для аудиту.
 */
class ImportLegacyRedirects extends Command
{
    protected $signature = 'otfk:legacy-redirects
        {file : CSV з колонками source,target,code,note}
        {--apply : Записати зміни; без опції лише звіт}
        {--archive-dir= : Каталог для копії CSV і звіту, типово storage/app/private/legacy-redirects}';

    protected $description = 'Імпорт карти редиректів старого сайту (301/308/410) з CSV: dry-run, потім --apply';

    public function handle(): int
    {
        $file = (string) $this->argument('file');
        if (! is_file($file) || ! is_readable($file)) {
            $this->error("Файл не знайдено: {$file}");

            return self::FAILURE;
        }

        $rows = $this->readCsv($file);
        [$plan, $conflicts] = $this->plan($rows);

        $counts = ['create' => 0, 'update' => 0, 'same' => 0];
        foreach ($plan as $item) {
            $counts[$item['kind']]++;
        }

        $this->table(['Нових', 'Змінених', 'Без змін', 'Конфліктів'], [[$counts['create'], $counts['update'], $counts['same'], count($conflicts)]]);
        foreach (array_slice($conflicts, 0, 50) as $c) {
            $this->warn("  рядок {$c['line']}: {$c['source']} — {$c['error']}");
        }
        if (count($conflicts) > 50) {
            $this->warn('  … ще '.(count($conflicts) - 50).' (повний перелік — у звіті з --apply)');
        }

        if (! $this->option('apply')) {
            $this->line('Dry-run: нічого не записано. Для запису — та сама команда з --apply.');

            return $conflicts === [] ? self::SUCCESS : self::FAILURE;
        }

        $written = 0;
        foreach ($plan as $index => $item) {
            if ($item['kind'] === 'same') {
                continue;
            }
            try {
                $record = LegacyRedirect::firstOrNew(['source_hash' => $item['hash']]);
                $record->fill($item['attributes'])->save();
                $written++;
            } catch (ValidationException $e) {
                $conflicts[] = ['line' => $item['line'], 'source' => $item['source'], 'error' => collect($e->errors())->flatten()->implode(' ')];
                unset($plan[$index]);
            }
        }

        $archive = $this->archive($file, $plan, $conflicts);
        $this->info("Записано: {$written}. Копія CSV і звіт: {$archive}");

        return $conflicts === [] ? self::SUCCESS : self::FAILURE;
    }

    /** @return list<array{line: int, source: string, target: string, code: string, note: string}> */
    private function readCsv(string $file): array
    {
        $handle = fopen($file, 'r');
        $rows = [];
        $columns = ['source' => 0, 'target' => 1, 'code' => 2, 'note' => 3];
        $line = 0;

        while (($data = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $line++;
            if ($line === 1) {
                $data[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $data[0]); // BOM з Excel
                $header = array_map(fn ($v) => strtolower(trim((string) $v)), $data);
                if (in_array('source', $header, true)) {
                    $columns = [];
                    foreach (['source', 'target', 'code', 'note'] as $name) {
                        if (($index = array_search($name, $header, true)) !== false) {
                            $columns[$name] = $index;
                        }
                    }

                    continue;
                }
            }
            if ($data === [null] || trim(implode('', $data)) === '') {
                continue;
            }
            $value = fn (string $key) => trim((string) (isset($columns[$key]) ? ($data[$columns[$key]] ?? '') : ''));
            $rows[] = ['line' => $line, 'source' => $value('source'), 'target' => $value('target'), 'code' => $value('code'), 'note' => $value('note')];
        }
        fclose($handle);

        return $rows;
    }

    /**
     * @return array{0: array<int, array{line: int, source: string, hash: string, kind: string, attributes: array}>, 1: list<array{line: int, source: string, error: string}>}
     */
    private function plan(array $rows): array
    {
        $plan = [];
        $conflicts = [];
        $seen = [];
        $conflict = function (array $row, string $error) use (&$conflicts) {
            $conflicts[] = ['line' => $row['line'], 'source' => $row['source'], 'error' => $error];
        };

        foreach ($rows as $row) {
            $parsed = LegacyRedirects::parseSource($row['source']);
            if (! $parsed) {
                $conflict($row, 'стара адреса має починатися з «/» або бути на основному домені');

                continue;
            }
            [$path, $query] = $parsed;
            $hash = LegacyRedirects::hash($path, $query);

            if (isset($seen[$hash])) {
                $conflict($row, "дубль рядка {$seen[$hash]}");

                continue;
            }
            $seen[$hash] = $row['line'];

            $code = $row['code'] === '' ? ($row['target'] === '' ? 0 : 301) : (int) $row['code'];
            $gone = $code === 410;
            if (! $gone && ! in_array($code, LegacyRedirect::REDIRECT_CODES, true)) {
                $conflict($row, 'код має бути 301, 308 або 410');

                continue;
            }
            if ($gone && $row['target'] !== '') {
                $conflict($row, '410 не має адреси призначення');

                continue;
            }
            if ($query === null && LegacyRedirects::resolves($path)) {
                $conflict($row, 'адреса зараз відповідає на новому сайті — редирект не потрібен');

                continue;
            }
            if (! $gone) {
                if ($error = LegacyRedirect::targetError($row['target'])) {
                    $conflict($row, $error);

                    continue;
                }
                if (! LegacyRedirects::resolves($row['target'])) {
                    $conflict($row, 'за адресою призначення немає опублікованої сторінки чи файлу');

                    continue;
                }
            }

            $existing = LegacyRedirect::where('source_hash', $hash)->first();
            $attributes = [
                'source_path' => $path,
                'source_query' => $query,
                'action' => $gone ? LegacyRedirect::GONE : LegacyRedirect::REDIRECT,
                'target_url' => $gone ? null : $row['target'],
                'status_code' => $gone ? 410 : $code,
                'is_active' => true,
                'note' => $row['note'] !== '' ? mb_substr($row['note'], 0, 500) : ($existing?->note),
            ];

            $errors = LegacyRedirect::problems($path, $query, $attributes['action'], $attributes['target_url'], $attributes['status_code'], $existing?->id);
            if ($errors !== []) {
                $conflict($row, implode(' ', $errors));

                continue;
            }

            $same = $existing && collect($attributes)->every(fn ($value, $key) => $existing->{$key} == $value);
            $plan[] = [
                'line' => $row['line'], 'source' => $row['source'], 'hash' => $hash,
                'kind' => $existing ? ($same ? 'same' : 'update') : 'create',
                'attributes' => $attributes,
            ];
        }

        // Ланцюжки всередині CSV: призначення рядка є старою адресою іншого рядка.
        $sources = array_flip(array_column($plan, 'hash'));
        foreach ($plan as $index => $item) {
            [$targetHash, $targetPathHash] = LegacyRedirect::targetHashes($item['attributes']['target_url']);
            if ($targetHash && (isset($sources[$targetHash]) || isset($sources[$targetPathHash]))) {
                $conflict($item, 'призначення саме є старою адресою в цьому CSV — вкажіть кінцеву адресу');
                unset($plan[$index]);
            }
        }

        usort($conflicts, fn ($a, $b) => $a['line'] <=> $b['line']);

        return [$plan, $conflicts];
    }

    private function archive(string $file, array $plan, array $conflicts): string
    {
        $dir = (string) ($this->option('archive-dir') ?: storage_path('app/private/legacy-redirects'));
        File::ensureDirectoryExists($dir);
        $stamp = now()->format('Y-m-d_His');

        File::copy($file, "{$dir}/{$stamp}_".basename($file));
        File::put("{$dir}/{$stamp}_report.json", json_encode([
            'file' => basename($file),
            'applied_at' => now()->toIso8601String(),
            'written' => array_values(array_map(fn ($i) => ['line' => $i['line'], 'kind' => $i['kind']] + $i['attributes'], array_filter($plan, fn ($i) => $i['kind'] !== 'same'))),
            'conflicts' => $conflicts,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $dir;
    }
}
