<?php

namespace App\Console\Commands;

use App\Support\LegacyMapBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Збирання карти старих адрес у CSV для otfk:legacy-redirects
 * (docs/seo-plan.md, етап 1): маркери imported-from, file_mirrors, фото новин,
 * активи сторінок і PDF документів. Лише читання БД і диска.
 *
 * Результат: <out> (source,target,code,note — формат імпорту), <out>.unmapped.csv
 * (адреси зі списків/sitemap без відповідності, непубліковані матеріали, фото без
 * відомого старого шляху — вирішує редактор; автоматичних 410 і редиректів на
 * головну немає) та <out>.conflicts.csv (колізії, різні призначення, ланцюжки,
 * живі старі адреси, невдала перевірка призначення).
 */
class BuildLegacyMap extends Command
{
    protected $signature = 'otfk:legacy-map
        {--out= : CSV карти, типово storage/app/private/legacy-map/legacy-map-ДАТА.csv}
        {--sitemap=* : Адреса sitemap старого сайту (підтримується sitemap index), можна кілька}
        {--urls=* : Файл зі списком старих адрес (рядок або перша колонка CSV), можна кілька}
        {--scan-dir= : Каталог збережених HTML/MD сторінок старого сайту для пошуку адрес файлів}
        {--old-host= : Домен старого сайту без www, типово SEO_PRIMARY_HOST}
        {--guess-photos : Для фото новин без відомого старого шляху писати /uploads/ІМЯ (лише явно)}
        {--verify : Перевірити кожне призначення: файл і sha256 зеркала, відповідь маршруту}';

    protected $description = 'Збирання карти старих адрес (CSV для otfk:legacy-redirects) із маркерів імпорту, file_mirrors і файлів';

    private const MAX_SITEMAPS = 200;

    public function handle(): int
    {
        $builder = new LegacyMapBuilder((string) ($this->option('old-host') ?: config('otfk.seo.primary_host')));

        foreach ((array) $this->option('urls') as $file) {
            if (! is_file($file) || ! is_readable($file)) {
                $this->error("Файл не знайдено: {$file}");

                return self::FAILURE;
            }
            $n = 0;
            foreach ($this->readUrlList($file) as $url) {
                $builder->addListedUrl($url, 'list');
                $n++;
            }
            $this->line("Список {$file}: {$n} адрес");
        }

        foreach ((array) $this->option('sitemap') as $sitemap) {
            $urls = $this->fetchSitemap($sitemap);
            foreach ($urls as $url) {
                $builder->addListedUrl($url, 'sitemap');
            }
            $this->line("Sitemap {$sitemap}: ".count($urls).' адрес');
        }

        if ($dir = $this->option('scan-dir')) {
            if (! is_dir($dir)) {
                $this->error("Каталог не знайдено: {$dir}");

                return self::FAILURE;
            }
            $this->line("Збережені сторінки {$dir}: ".$builder->scanDirectory($dir).' посилань на старий домен');
        }

        $result = $builder->build((bool) $this->option('verify'), (bool) $this->option('guess-photos'));

        $out = (string) ($this->option('out') ?: storage_path('app/private/legacy-map/legacy-map-'.now()->format('Y-m-d_His').'.csv'));
        File::ensureDirectoryExists(dirname($out));
        $this->writeCsv($out, ['source', 'target', 'code', 'note'], array_map(fn ($r) => [$r['source'], $r['target'], $r['code'], $r['note']], $result['rows']));
        $this->writeCsv($out.'.unmapped.csv', ['source', 'reason', 'suggested_target', 'origin', 'note'], array_map('array_values', $result['unmapped']));
        $this->writeCsv($out.'.conflicts.csv', ['source', 'target', 'type', 'origin', 'detail'], array_map('array_values', $result['conflicts']));

        $this->summary($result);
        $this->info("Карта: {$out}");
        $this->line("Без відповідності: {$out}.unmapped.csv; конфлікти: {$out}.conflicts.csv");
        $this->line('Далі: php artisan otfk:legacy-redirects '.$out.' (dry-run), потім з --apply.');

        return self::SUCCESS;
    }

    private function summary(array $result): void
    {
        $columns = ['mapped', 'unchanged', 'live', 'unpublished', 'unconfirmed', 'unmapped', 'conflict', 'verify-failed', 'foreign'];
        $rows = [];
        foreach ($result['stats'] as $origin => $counts) {
            $rows[] = array_merge([$origin], array_map(fn ($c) => $counts[$c] ?? 0, $columns));
        }
        usort($rows, fn ($a, $b) => $a[0] <=> $b[0]);
        $this->table(array_merge(['Джерело'], $columns), $rows);
        $this->line(sprintf('Рядків карти: %d; без відповідності: %d; конфліктів: %d; адрес іншого домену у списках: %d',
            count($result['rows']), count($result['unmapped']), count($result['conflicts']), $result['foreign']));
    }

    /** @return list<string> */
    private function readUrlList(string $file): array
    {
        $urls = [];
        $handle = fopen($file, 'r');
        while (($data = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $value = trim((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) ($data[0] ?? '')));
            if ($value === '' || str_starts_with($value, '#') || (! str_starts_with($value, '/') && ! preg_match('~^https?://~i', $value))) {
                continue; // порожні рядки, коментарі й заголовок CSV
            }
            $urls[] = $value;
        }
        fclose($handle);

        return $urls;
    }

    /**
     * Адреси з sitemap або sitemap index (вкладені файли обходяться, не більше MAX_SITEMAPS).
     *
     * @return list<string>
     */
    private function fetchSitemap(string $url): array
    {
        $queue = [$url];
        $seen = [];
        $urls = [];

        while ($queue !== [] && count($seen) < self::MAX_SITEMAPS) {
            $current = array_shift($queue);
            if (isset($seen[$current])) {
                continue;
            }
            $seen[$current] = true;

            try {
                // Старий сайт має проблемний сертифікат — так само, як імпорт-команди otfk:import-*.
                $response = Http::withoutVerifying()->timeout(30)->get($current);
            } catch (Throwable $e) {
                $this->warn("  sitemap недоступний: {$current} ({$e->getMessage()})");

                continue;
            }
            if (! $response->successful()) {
                $this->warn("  sitemap {$current}: HTTP {$response->status()}");

                continue;
            }

            $xml = $response->body();
            preg_match_all('~<loc>\s*(.*?)\s*</loc>~si', $xml, $m);
            $locs = array_map(fn ($v) => html_entity_decode(preg_replace('~^<!\[CDATA\[(.*)\]\]>$~s', '$1', $v), ENT_QUOTES | ENT_XML1, 'UTF-8'), $m[1]);

            if (preg_match('~<sitemapindex\b~i', $xml)) {
                array_push($queue, ...$locs);
            } else {
                array_push($urls, ...$locs);
            }
        }

        return array_values(array_unique($urls));
    }

    private function writeCsv(string $file, array $header, array $rows): void
    {
        $handle = fopen($file, 'w');
        fputcsv($handle, $header, ',', '"', '', "\n");
        foreach ($rows as $row) {
            fputcsv($handle, $row, ',', '"', '', "\n");
        }
        fclose($handle);
    }
}
