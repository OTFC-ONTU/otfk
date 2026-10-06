<?php

namespace App\Console\Commands\Concerns;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Читання ЛОКАЛЬНОГО дзеркала старого сайту otfk.od.ua (site-audit/YYYY-MM-DD/):
 * markdown-експорт сторінок (content-export/*.md), збережені HTML (_raw/html/),
 * документи (content-export/files/) та зображення (content-export/images/).
 *
 * Використовується імпорт-командами у режимі --from-export=<шлях-до-дзеркала>,
 * щоб не скрейпити живий сайт повторно.
 */
trait ReadsOtfkExport
{
    /** Корінь дзеркала (каталог, що містить content-export/ та _raw/). */
    protected ?string $exportRoot = null;

    /** Кеш links-map.csv: original_url => абсолютний локальний шлях. */
    protected ?array $exportLinksMap = null;

    /** Перевіряє та запам'ятовує корінь дзеркала. */
    protected function initExport(string $path): bool
    {
        $real = realpath($path);

        if ($real === false || ! is_dir($real . '/content-export')) {
            $this->error("Каталог дзеркала не знайдено або він без content-export/: {$path}");

            return false;
        }

        $this->exportRoot = $real;

        return true;
    }

    /** Абсолютний шлях усередині дзеркала. */
    protected function exportPath(string $relative = ''): string
    {
        return rtrim($this->exportRoot . '/' . ltrim($relative, '/'), '/');
    }

    /** Нормалізує старий URL/шлях до "/section/page" (без домену, index.php та трейлінг-слешів). */
    protected function normalizeOldPath(string $url): string
    {
        $url = str_replace('\\', '/', trim($url));
        $path = preg_match('#^(?:https?:)?//#i', $url) ? (parse_url($url, PHP_URL_PATH) ?: '/') : $url;
        $path = '/' . ltrim(rawurldecode($path), '/');
        $path = preg_replace('#/index\.php$#u', '', $path);

        return rtrim($path, '/') ?: '/';
    }

    /** Розв'язує відносне посилання (./x, ../x, x) відносно КАТАЛОГУ старої сторінки. */
    protected function resolveOldPath(string $href, string $pageDir): string
    {
        $href = str_replace('\\', '/', trim($href));

        if ($href === '' || Str::startsWith($href, ['http://', 'https://', '//', '#', 'mailto:', 'tel:'])) {
            return $href;
        }

        if (Str::startsWith($href, '/')) {
            return $href;
        }

        $base = rtrim($pageDir, '/');
        $href = preg_replace('#^\./#', '', $href);

        while (Str::startsWith($href, '../')) {
            $href = substr($href, 3);
            $base = dirname($base);
            $base = $base === '/' || $base === '\\' || $base === '.' ? '' : $base;
        }

        return $base . '/' . $href;
    }

    /**
     * Розбирає md-файл експорту. Формат: "# Заголовок", рядки "URL:" і "Опис:",
     * далі тіло (повторний заголовок + контент сторінки).
     *
     * @return array{title: string, url: string, path: string, description: ?string, body: string}|null
     */
    protected function parseExportMarkdown(string $file): ?array
    {
        if (! is_file($file)) {
            return null;
        }

        $raw = (string) file_get_contents($file);

        if (! preg_match('/^#\s+(.+)$/mu', $raw, $t)) {
            return null;
        }
        $title = trim($t[1]);

        if (! preg_match('/^URL:\s*(\S+)/mu', $raw, $u)) {
            return null;
        }
        $url = trim($u[1]);

        $description = preg_match('/^Опис:\s*(.+)$/mu', $raw, $d) ? Str::squish($d[1]) : null;

        // Тіло — після ПОВТОРНОГО заголовка (другого рядка "# ..."), інакше після шапки.
        $headings = [];
        preg_match_all('/^#\s+.+$/mu', $raw, $h, PREG_OFFSET_CAPTURE);
        $headings = $h[0];
        if (count($headings) >= 2) {
            $body = substr($raw, $headings[1][1] + strlen($headings[1][0]));
        } else {
            $pos = strpos($raw, $u[0]);
            $body = substr($raw, $pos + strlen($u[0]));
            if ($description !== null && preg_match('/^Опис:.*$/mu', $body, $dd, PREG_OFFSET_CAPTURE)) {
                $body = substr($body, $dd[0][1] + strlen($dd[0][0]));
            }
        }

        // Службовий лінк «<<< Новини» тощо.
        $body = preg_replace('/^\[<+[^\]]*\]\([^)]*\)\s*$/mu', '', $body);

        return [
            'title' => $title,
            'url' => $url,
            'path' => $this->normalizeOldPath($url),
            'description' => $description,
            'body' => trim($body),
        ];
    }

    /** Збережений HTML старої сторінки з _raw/html/ (шлях "/a/b/" → "a_b.html"). */
    protected function exportRawHtml(string $oldPath): ?string
    {
        $name = trim(str_replace('/', '_', $this->normalizeOldPath($oldPath) === '/'
            ? 'index'
            : ltrim($this->normalizeOldPath($oldPath), '/')), '_');

        // У дзеркалі /applicant/info_/index.php збережено як applicant_info__index.php.html.
        $candidates = [$name . '.html', str_replace('/', '_', ltrim(rtrim($oldPath, '/'), '/')) . '.html'];

        foreach (array_unique($candidates) as $c) {
            $file = $this->exportPath('_raw/html/' . $c);
            if (is_file($file)) {
                return (string) file_get_contents($file);
            }

            // Деякі старі шляхи містять кириличні двійники латиниці (…/сivil_…):
            // шукаємо файл, що збігається після нормалізації таких символів.
            $found = $this->findLookalikeFile($this->exportPath('_raw/html'), $c);
            if ($found !== null) {
                return (string) file_get_contents($found);
            }
        }

        return null;
    }

    /** Файл каталогу, ім'я якого збігається з $name після заміни кириличних двійників латиниці. */
    protected function findLookalikeFile(string $dir, string $name): ?string
    {
        static $cache = [];

        if (! isset($cache[$dir])) {
            $cache[$dir] = [];
            foreach (is_dir($dir) ? scandir($dir) : [] as $f) {
                $cache[$dir][$this->foldLookalikes($f)] = $dir . '/' . $f;
            }
        }

        return $cache[$dir][$this->foldLookalikes($name)] ?? null;
    }

    /** Замінює кириличні символи-двійники латиницею (с→c, о→o, …) для порівняння імен. */
    protected function foldLookalikes(string $s): string
    {
        return strtr($s, [
            'а' => 'a', 'е' => 'e', 'і' => 'i', 'о' => 'o', 'р' => 'p', 'с' => 'c', 'у' => 'y', 'х' => 'x',
            'А' => 'A', 'Е' => 'E', 'І' => 'I', 'О' => 'O', 'Р' => 'P', 'С' => 'C', 'У' => 'Y', 'Х' => 'X',
        ]);
    }

    /** Мапа links-map.csv: original_url => абсолютний локальний файл. */
    protected function exportLinksMap(): array
    {
        if ($this->exportLinksMap !== null) {
            return $this->exportLinksMap;
        }

        $this->exportLinksMap = [];
        $csv = $this->exportPath('content-export/links-map.csv');

        if (is_file($csv) && ($fh = fopen($csv, 'r')) !== false) {
            fgetcsv($fh); // заголовок
            while (($row = fgetcsv($fh)) !== false) {
                if (count($row) >= 2 && $row[0] !== '' && $row[1] !== '') {
                    $this->exportLinksMap[$row[0]] = $this->exportPath($row[1]);
                }
            }
            fclose($fh);
        }

        return $this->exportLinksMap;
    }

    /** Локальний файл дзеркала для старого шляху/URL зображення чи документа. */
    protected function exportAssetFile(string $oldPathOrUrl): ?string
    {
        $path = str_replace('\\', '/', trim($oldPathOrUrl));
        if (preg_match('#^(?:https?:)?//#i', $path)) {
            $host = parse_url($path, PHP_URL_HOST) ?: '';
            if (! Str::contains($host, 'otfk.od.ua')) {
                // Зовнішній файл — лише через links-map.csv.
                $local = $this->exportLinksMap()[$oldPathOrUrl] ?? null;

                return $local && is_file($local) ? $local : null;
            }
            $path = parse_url($path, PHP_URL_PATH) ?: '';
        }

        $rel = ltrim(rawurldecode($path), '/');

        foreach (['content-export/images/', 'content-export/files/'] as $dir) {
            $file = $this->exportPath($dir . $rel);
            if (is_file($file)) {
                return $file;
            }

            // Кириличні двійники латиниці у сегментах шляху (…/сivil_…/files/x.pdf).
            $resolved = $this->exportPath(rtrim($dir, '/'));
            foreach (explode('/', $rel) as $segment) {
                $next = $resolved . '/' . $segment;
                if (! file_exists($next)) {
                    $next = $this->findLookalikeFile($resolved, $segment);
                }
                if ($next === null) {
                    $resolved = null;
                    break;
                }
                $resolved = $next;
            }
            if ($resolved !== null && is_file($resolved)) {
                return $resolved;
            }
        }

        foreach ([$oldPathOrUrl, 'https://otfk.od.ua/' . $rel, 'https://www.otfk.od.ua/' . $rel] as $key) {
            $local = $this->exportLinksMap()[$key] ?? null;
            if ($local && is_file($local)) {
                return $local;
            }
        }

        return null;
    }

    /**
     * Копіює файл дзеркала на публічний диск (ідемпотентно: наявний не перезаписує).
     * Повертає шлях на диску public або null, якщо файла немає у дзеркалі.
     */
    protected function copyExportAsset(string $oldPathOrUrl, string $target): ?string
    {
        $disk = Storage::disk('public');

        if ($disk->exists($target)) {
            return $target;
        }

        $file = $this->exportAssetFile($oldPathOrUrl);

        if ($file === null) {
            return null;
        }

        $stream = fopen($file, 'r');
        $disk->put($target, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }

        return $target;
    }

    /** Шлях на диску public для сторінкових активів: зберігає стару структуру каталогів. */
    protected function importedAssetTarget(string $oldPath, string $prefix = 'imported'): string
    {
        $rel = ltrim(rawurldecode(str_replace('\\', '/', $oldPath)), '/');
        $rel = preg_replace('/[^\pL\pN_\-.\/]+/u', '_', $rel);
        $kind = preg_match('/\.(jpe?g|png|gif|webp|svg)$/i', $rel) ? 'images' : 'files';

        return $prefix . '/' . $kind . '/' . $rel;
    }

    /**
     * Тіло сторінки з md-експорту → HTML для поля body:
     * зображення та документи копіюються у storage (диск public, каталог imported/…),
     * внутрішні посилання резолвляться через $pageLink (старий шлях → наш URL або null),
     * нерозв'язані внутрішні лишаються абсолютними на старий сайт.
     *
     * @param  callable(string): ?string  $pageLink
     */
    protected function convertExportBodyToHtml(string $markdown, string $oldPath, ?callable $pageLink = null, string $assetPrefix = 'imported'): string
    {
        $pageDir = $this->normalizeOldPath($oldPath);
        $fileExt = 'pdf|docx?|xlsx?|pptx?|zip|rar|7z|txt|rtf|odt|ods';

        // Зображення: копія у storage або видалення тега, якщо файла немає у дзеркалі.
        $markdown = preg_replace_callback('/!\[([^\]]*)\]\(([^)\s]+)(?:\s+"[^"]*")?\)/u', function ($m) use ($pageDir, $assetPrefix) {
            $old = $this->resolveOldPath($m[2], $pageDir);

            if (Str::startsWith($old, ['http://', 'https://', '//']) && ! Str::contains($old, 'otfk.od.ua')) {
                return ''; // зовнішні картинки не тягнемо
            }

            $old = $this->normalizeAsset($old);
            $saved = $this->copyExportAsset($old, $this->importedAssetTarget($old, $assetPrefix));

            return $saved === null ? '' : '![' . $m[1] . '](/storage/' . $saved . ')';
        }, $markdown);

        // Посилання: документи → storage; сторінки → наш URL або старий абсолютний.
        $markdown = preg_replace_callback('/(?<!\!)\[([^\]]+)\]\(([^)\s]+)(?:\s+"[^"]*")?\)/u', function ($m) use ($pageDir, $pageLink, $fileExt, $assetPrefix) {
            [$all, $text, $href] = $m;

            if (preg_match('#^(mailto:|tel:|\#)#i', $href)) {
                return $all;
            }

            $isOld = ! Str::startsWith($href, ['http://', 'https://', '//']) || Str::contains($href, 'otfk.od.ua');

            if (! $isOld) {
                return $all; // зовнішнє посилання — як є
            }

            $old = $this->resolveOldPath($href, $pageDir);
            $old = $this->normalizeAsset($old);

            if (preg_match('/\.(' . $fileExt . ')(\?|$)/iu', $old)) {
                $saved = $this->copyExportAsset($old, $this->importedAssetTarget($old, $assetPrefix));

                return '[' . $text . '](' . ($saved !== null ? '/storage/' . $saved : 'https://otfk.od.ua' . $old) . ')';
            }

            $path = $this->normalizeOldPath($old);
            $local = $pageLink ? $pageLink($path) : null;

            return '[' . $text . '](' . ($local ?? 'https://otfk.od.ua' . $path) . ')';
        }, $markdown);

        // Вбудовані фрейми "[embed: URL]" зберігаємо токеном і відновлюємо після конвертації.
        $embeds = [];
        $markdown = preg_replace_callback('/\[embed:\s*(\S+?)\](?:\((\S+?)\))?/u', function ($m) use (&$embeds, $pageDir, $fileExt, $assetPrefix) {
            $url = $m[2] ?? $m[1];

            // Вбудований файл старого сайту → копія у storage.
            $isOld = ! Str::startsWith($url, ['http://', 'https://', '//']) || Str::contains($url, 'otfk.od.ua');
            if ($isOld) {
                $old = $this->normalizeAsset($this->resolveOldPath($url, $pageDir));
                if (preg_match('/\.(' . $fileExt . ')(\?|$)/iu', $old)) {
                    $saved = $this->copyExportAsset($old, $this->importedAssetTarget($old, $assetPrefix));
                    $url = $saved !== null ? '/storage/' . $saved : 'https://otfk.od.ua' . $old;
                } else {
                    $url = 'https://otfk.od.ua' . $old;
                }
            } elseif (! preg_match('#^https://#', $url)) {
                return '';
            }

            $embeds[] = $url;

            return '%%OTFK-EMBED-' . (count($embeds) - 1) . '%%';
        }, $markdown);

        $html = $this->exportMarkdownToHtml($markdown);

        foreach ($embeds as $i => $url) {
            $iframe = '<iframe src="' . e($url) . '" class="w-full" style="min-height:24rem;border:0" loading="lazy"></iframe>';
            $html = str_replace('%%OTFK-EMBED-' . $i . '%%', $iframe, $html);
        }

        return trim($html);
    }

    /** Прибирає з шляху активу домен і бекслеші (без відкидання index.php). */
    protected function normalizeAsset(string $pathOrUrl): string
    {
        $s = str_replace('\\', '/', trim($pathOrUrl));

        if (preg_match('#^(?:https?:)?//#i', $s)) {
            $s = parse_url($s, PHP_URL_PATH) ?: '/';
        }

        return '/' . ltrim(rawurldecode($s), '/');
    }

    /** Markdown експорту → безпечний HTML (GFM: таблиці, списки). */
    protected function exportMarkdownToHtml(string $markdown): string
    {
        return trim((string) Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]));
    }
}
