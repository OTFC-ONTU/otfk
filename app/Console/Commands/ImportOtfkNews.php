<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ReadsOtfkExport;
use App\Models\News;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportOtfkNews extends Command
{
    use ReadsOtfkExport;

    protected $signature = 'otfk:import-news
        {--dry-run : Показати знайдене, нічого не зберігати}
        {--limit=0 : Імпортувати лише перші N новин (0 = всі)}
        {--from-export= : Читати з локального дзеркала site-audit/… замість HTTP}
        {--fresh : Видалити попередній імпорт новин перед завантаженням}';

    protected $description = 'Імпорт новин зі старого сайту otfk.od.ua: текст, дати та фото (у storage/news/imported)';

    private string $base = 'https://otfk.od.ua';

    /** Каталог на публічному диску для фото новин. */
    private string $dir = 'news/imported';

    /** Українські родові відмінки місяців → номер. */
    private array $months = [
        'січня' => 1, 'лютого' => 2, 'березня' => 3, 'квітня' => 4,
        'травня' => 5, 'червня' => 6, 'липня' => 7, 'серпня' => 8,
        'вересня' => 9, 'жовтня' => 10, 'листопада' => 11, 'грудня' => 12,
    ];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $limit = max(0, (int) $this->option('limit'));

        if ($this->option('fresh') && ! $dry) {
            $this->purge();
        }

        if (filled($this->option('from-export'))) {
            return $this->handleFromExport($dry, $limit);
        }

        $this->info('Читаю список новин зі старого сайту…');
        $links = $this->newsLinks();

        if ($links === []) {
            $this->error('Не вдалося отримати список новин (сайт недоступний?).');

            return self::FAILURE;
        }

        $this->line('Знайдено новин у списку: ' . count($links));

        if ($limit > 0) {
            $links = array_slice($links, 0, $limit);
            $this->line("Обмеження --limit: беремо перші {$limit}.");
        }

        // Вже імпортовані джерела (маркер у тілі) — для повторних запусків.
        $imported = News::query()
            ->where('body', 'like', '%<!--imported-from:%')
            ->pluck('body')
            ->map(fn ($b) => preg_match('/<!--imported-from:(.+?)-->/u', $b, $m) ? $m[1] : null)
            ->filter()
            ->flip();

        $done = 0;
        $skipped = 0;
        $failed = [];

        foreach ($links as $i => $path) {
            $source = $this->base . $path;

            if (isset($imported[$source])) {
                $skipped++;

                continue;
            }

            try {
                $article = $this->parseArticle($path, $i);
            } catch (\Throwable $e) {
                $failed[] = [$path, Str::limit($e->getMessage(), 80)];

                continue;
            }

            if ($article === null) {
                $failed[] = [$path, 'не розпізнано вміст статті'];

                continue;
            }

            if ($dry) {
                $this->line(sprintf(
                    '[dry] %s | %s | фото: %d',
                    $article['published_at']->format('d.m.Y'),
                    Str::limit($article['title'], 60),
                    $article['images']
                ));
                $done++;

                continue;
            }

            News::create([
                'title' => $article['title'],
                'slug' => $article['slug'],
                'excerpt' => $article['excerpt'],
                'body' => $article['body'] . "\n<!--imported-from:{$source}-->",
                'cover_image' => $article['cover'],
                'published_at' => $article['published_at'],
                'is_published' => true,
                // Імпортовані новини НЕ постимо в Telegram (їх сотні — позначаємо як уже опубліковані)
                'telegram_posted_at' => now(),
            ]);

            $done++;

            if ($done % 10 === 0) {
                $this->line("  …{$done} імпортовано");
            }

            usleep(150_000); // не дудосимо старий сайт
        }

        $this->newLine();
        $this->info(($dry ? 'Знайдено для імпорту: ' : 'Імпортовано: ') . $done
            . ($skipped ? ", пропущено (вже є): {$skipped}" : '')
            . ($failed ? ', помилок: ' . count($failed) : ''));

        if ($failed !== []) {
            $this->table(['Сторінка', 'Помилка'], $failed);
        }

        return self::SUCCESS;
    }

    /**
     * Імпорт із ЛОКАЛЬНОГО дзеркала (site-audit/…): content-export/news/*.md +
     * фото з content-export/images/. Маркери ідемпотентності ті самі, що й у
     * HTTP-режимі (<!--imported-from:…-->), тож режими взаємозамінні.
     */
    private function handleFromExport(bool $dry, int $limit): int
    {
        if (! $this->initExport((string) $this->option('from-export'))) {
            return self::FAILURE;
        }

        $files = glob($this->exportPath('content-export/news') . '/*.md') ?: [];
        sort($files);

        if ($files === []) {
            $this->error('У дзеркалі немає content-export/news/*.md.');

            return self::FAILURE;
        }

        $this->line('Знайдено новин у дзеркалі: ' . count($files));

        // Розбираємо всі, сортуємо за датою (новіші першими) — щоб зсув секунд
        // давав той самий порядок у межах дня, що й HTTP-режим.
        $articles = [];
        $failed = [];

        foreach ($files as $file) {
            $md = $this->parseExportMarkdown($file);

            if ($md === null || blank($md['title'])) {
                $failed[] = [basename($file), 'не розпізнано шапку md-файла'];

                continue;
            }

            $published = now()->setTime(12, 0);
            if (preg_match('/(\d{1,2})\s+(' . implode('|', array_keys($this->months)) . ')\s+(\d{4})/u', $md['body'], $d)) {
                $published = Carbon::create((int) $d[3], $this->months[$d[2]], (int) $d[1], 12, 0, 0);
            }

            $articles[] = ['md' => $md, 'published' => $published, 'file' => $file];
        }

        usort($articles, fn ($a, $b) => $b['published'] <=> $a['published']);

        if ($limit > 0) {
            $articles = array_slice($articles, 0, $limit);
            $this->line("Обмеження --limit: беремо перші {$limit}.");
        }

        // Вже імпортовані джерела (маркер у тілі) — для повторних запусків.
        $imported = News::query()
            ->where('body', 'like', '%<!--imported-from:%')
            ->pluck('body')
            ->map(fn ($b) => preg_match('/<!--imported-from:(.+?)-->/u', $b, $m) ? $m[1] : null)
            ->filter()
            ->flip();

        $done = 0;
        $skipped = 0;

        foreach ($articles as $i => $item) {
            $md = $item['md'];
            $source = $this->base . $md['path'] . '/';

            if (isset($imported[$source]) || isset($imported[rtrim($source, '/')])) {
                $skipped++;

                continue;
            }

            $article = $this->buildArticleFromExport($md, $item['published']->subSeconds($i), $dry);

            if ($article === null) {
                $failed[] = [basename($item['file']), 'порожня стаття'];

                continue;
            }

            if ($dry) {
                $this->line(sprintf(
                    '[dry] %s | %s | фото: %d',
                    $article['published_at']->format('d.m.Y'),
                    Str::limit($article['title'], 60),
                    $article['images']
                ));
                $done++;

                continue;
            }

            News::create([
                'title' => $article['title'],
                'slug' => $article['slug'],
                'excerpt' => $article['excerpt'],
                'body' => $article['body'] . "\n<!--imported-from:{$source}-->",
                'cover_image' => $article['cover'],
                'published_at' => $article['published_at'],
                'is_published' => true,
                // Імпортовані новини НЕ постимо в Telegram (позначаємо як уже опубліковані).
                'telegram_posted_at' => now(),
            ]);

            $done++;

            if ($done % 50 === 0) {
                $this->line("  …{$done} імпортовано");
            }
        }

        $this->newLine();
        $this->info(($dry ? 'Знайдено для імпорту: ' : 'Імпортовано: ') . $done
            . ($skipped ? ", пропущено (вже є): {$skipped}" : '')
            . ($failed ? ', помилок: ' . count($failed) : ''));

        if ($failed !== []) {
            $this->table(['Файл', 'Помилка'], $failed);
        }

        return self::SUCCESS;
    }

    /** Markdown-стаття дзеркала → поля запису News (фото копіюються у storage/news/imported). */
    private function buildArticleFromExport(array $md, Carbon $published, bool $dry): ?array
    {
        $body = $md['body'];
        $pageDir = $md['path']; // фото у новинах — /uploads/…, але буває і відносне

        // Прибираємо дублікат дати з тіла.
        $body = preg_replace('/^\s*\d{1,2}\s+(?:' . implode('|', array_keys($this->months)) . ')\s+\d{4}\s*$/mu', '', $body);

        // Фото: копіюємо з дзеркала, перше стає обкладинкою.
        $images = 0;
        $cover = null;
        $body = preg_replace_callback('/!\[[^\]]*\]\(([^)\s]+)(?:\s+"[^"]*")?\)/u', function ($m) use (&$images, &$cover, $pageDir, $dry) {
            $old = $this->resolveOldPath($m[1], $pageDir);
            $name = basename(parse_url(str_replace('\\', '/', $old), PHP_URL_PATH) ?: '');

            if ($name === '' || ! preg_match('/\.(jpe?g|png|gif|webp)$/i', $name)) {
                return '';
            }

            $saved = $dry
                ? ($this->exportAssetFile($old) ? $this->dir . '/' . $name : null)
                : $this->copyExportAsset($old, $this->dir . '/' . $name);

            if ($saved === null) {
                return ''; // фото немає у дзеркалі — тег прибираємо
            }

            $images++;

            if ($cover === null) {
                $cover = $saved; // перше фото стає обкладинкою і з тіла прибирається

                return '';
            }

            return '<img src="/storage/' . $saved . '" alt="" loading="lazy" decoding="async">';
        }, $body);

        // Посилання: відносні стають абсолютними на старий сайт (як у HTTP-режимі).
        $body = preg_replace_callback('/(?<!\!)\[([^\]]*)\]\(([^)\s]+)(?:\s+"[^"]*")?\)/u', function ($m) use ($pageDir) {
            $href = $this->resolveOldPath($m[2], $pageDir);

            if ($href !== '' && ! preg_match('#^(https?:|mailto:|tel:|\#)#i', $href)) {
                $href = $this->base . '/' . ltrim($href, '/');
            }

            return '[' . $m[1] . '](' . $href . ')';
        }, $body);

        $html = $this->exportMarkdownToHtml($body);

        // Зовнішні посилання відкриваємо у новій вкладці — як у HTTP-режимі.
        $html = preg_replace('/<a href="(https?:[^"]+)">/u', '<a href="$1" target="_blank" rel="noopener">', $html);
        $html = trim($html);

        if (blank(strip_tags($html)) && $cover === null) {
            return null;
        }

        // Анотація: «Опис» із шапки або перший змістовний абзац.
        $excerpt = $md['description'] ?: null;
        if (blank($excerpt) && preg_match('#<p>(.*?)</p>#su', $html, $p)) {
            $excerpt = Str::limit(trim(html_entity_decode(strip_tags($p[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8')), 180);
        }

        return [
            'title' => Str::limit($md['title'], 250, ''),
            'slug' => $this->uniqueSlug($md['title'], $md['path']),
            'excerpt' => $excerpt ? Str::limit($excerpt, 180) : null,
            'body' => $html,
            'cover' => $cover,
            'published_at' => $published,
            'images' => $images,
        ];
    }

    /** Список шляхів /news/…/ зі сторінки-переліку (новіші першими). */
    private function newsLinks(): array
    {
        $html = $this->fetch('/news/');

        if ($html === null) {
            return [];
        }

        preg_match_all('#href="(/news/[^"/]+/)"#u', $html, $m);

        return array_values(array_unique($m[1]));
    }

    /**
     * Розбирає сторінку статті: заголовок, дата, тіло (очищене), фото.
     * Повертає null, якщо структура не розпізнана.
     */
    private function parseArticle(string $path, int $index): ?array
    {
        $html = $this->fetch($path);

        if ($html === null) {
            throw new \RuntimeException('сторінка недоступна');
        }

        // Заголовок — з <title>.
        $title = preg_match('#<title>(.*?)</title>#su', $html, $m)
            ? trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'))
            : null;

        // Вміст — усередині <article>.
        if (! preg_match('#<article[^>]*>(.*?)</article>#su', $html, $m)) {
            return null;
        }
        $body = $m[1];

        if (blank($title)) {
            return null;
        }

        // Дата: «06 червня 2026». Новіші статті отримують пізніший час доби,
        // щоб порядок у межах одного дня збігався зі старим сайтом.
        $published = now()->setTime(12, 0);
        if (preg_match('/(\d{1,2})\s+(' . implode('|', array_keys($this->months)) . ')\s+(\d{4})/u', $body, $d)) {
            $published = Carbon::create((int) $d[3], $this->months[$d[2]], (int) $d[1], 12, 0, 0);
        }
        $published = $published->subSeconds($index);

        // Прибираємо службове: лінк «<<< Новини», скрипти, форми, коментарі, заголовки, лічильник, дату.
        $body = preg_replace('#<a[^>]*href="[^"]*?/news/"[^>]*>.*?</a>#su', '', $body);
        // Голі «<» поза тегами (як-от «<<<») ламають strip_tags — екрануємо.
        $body = preg_replace('/<(?![a-zA-Z\/!])/u', '&lt;', $body);
        $body = preg_replace('#<script\b.*?</script>#su', '', $body);
        $body = preg_replace('#<form\b.*?</form>#su', '', $body);
        $body = preg_replace('#<!--.*?-->#su', '', $body);
        $body = preg_replace('#<h[12]\b[^>]*>.*?</h[12]>#su', '', $body);
        $body = preg_replace('#<p[^>]*class="Visitors"[^>]*>.*?</p>#su', '', $body);
        $body = preg_replace('/<p[^>]*>\s*\d{1,2}\s+(?:' . implode('|', array_keys($this->months)) . ')\s+\d{4}\s*<\/p>/u', '', $body);

        // Фото: завантажуємо /uploads/* до нас, переписуємо src.
        $images = 0;
        $cover = null;
        $body = preg_replace_callback('#<img\b[^>]*src="([^"]+)"[^>]*>#u', function ($img) use (&$images, &$cover) {
            $saved = $this->downloadImage($img[1]);

            if ($saved === null) {
                return ''; // бите фото — прибираємо тег
            }

            $images++;

            if ($cover === null) {
                $cover = $saved; // перше фото стає обкладинкою і з тіла прибирається

                return '';
            }

            return '<img src="/storage/' . $saved . '" alt="" loading="lazy" decoding="async">';
        }, $body);

        // Посилання: відносні стають абсолютними на старий сайт (документи тощо).
        $body = preg_replace_callback('#<a\b[^>]*href="([^"]*)"[^>]*>#u', function ($a) {
            $href = trim($a[1]);

            if ($href !== '' && ! preg_match('#^(https?:|mailto:|tel:|\#)#i', $href)) {
                $href = $this->base . '/' . ltrim(str_replace('\\', '/', $href), '/');
            }

            return '<a href="' . e($href) . '" target="_blank" rel="noopener">';
        }, $body);

        // Зачистка розмітки: лишаємо мінімум тегів, без атрибутів у <p>.
        $body = preg_replace('#<p\b[^>]*>#u', '<p>', $body);
        $body = preg_replace('#</?br[^>]*>#u', '<br>', $body);
        $body = strip_tags($body, '<p><br><a><img><ul><ol><li><strong><b><em><i><h3><h4><blockquote><table><thead><tbody><tr><td><th>');
        $body = preg_replace('#<p>(\s|&nbsp;|<br>)*</p>#u', '', $body);
        $body = trim(preg_replace("/\n{3,}/", "\n\n", $body));

        if (blank(strip_tags($body)) && $cover === null) {
            return null; // зовсім порожня стаття
        }

        // Анотація: перший змістовний абзац.
        $excerpt = null;
        if (preg_match('#<p>(.*?)</p>#su', $body, $p)) {
            $excerpt = Str::limit(trim(html_entity_decode(strip_tags($p[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8')), 180);
        }

        return [
            'title' => Str::limit($title, 250, ''),
            'slug' => $this->uniqueSlug($title, $path),
            'excerpt' => $excerpt,
            'body' => $body,
            'cover' => $cover,
            'published_at' => $published,
            'images' => $images,
        ];
    }

    /** Унікальний slug: з назви, при зайнятості — із суфіксом. */
    private function uniqueSlug(string $title, string $path): string
    {
        $slug = Str::slug(Str::limit($title, 90, '')) ?: Str::slug(trim($path, '/'));

        $base = $slug;
        $n = 2;
        while (News::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$n}";
            $n++;
        }

        return $slug;
    }

    /** Завантажує фото зі старого сайту на публічний диск. Повертає шлях або null. */
    private function downloadImage(string $src): ?string
    {
        $src = str_replace('\\', '/', trim($src));

        if (! str_starts_with($src, '/')) {
            $src = '/' . $src;
        }

        $name = basename(parse_url($src, PHP_URL_PATH) ?: '');

        if ($name === '' || ! preg_match('/\.(jpe?g|png|gif|webp)$/i', $name)) {
            return null;
        }

        $target = $this->dir . '/' . $name;

        if (Storage::disk('public')->exists($target)) {
            return $target; // вже завантажене (повторний запуск)
        }

        try {
            $resp = Http::withoutVerifying()->timeout(30)->get($this->base . $this->encodePath($src));
        } catch (\Throwable) {
            return null;
        }

        $bytes = $resp->body();

        // Магічні байти: JPEG / PNG / GIF / WebP(RIFF)
        $ok = $resp->successful() && strlen($bytes) > 100 && preg_match('/^(\xFF\xD8|\x89PNG|GIF8|RIFF)/', $bytes);

        if (! $ok) {
            return null;
        }

        Storage::disk('public')->put($target, $bytes);

        return $target;
    }

    /** GET сторінки старого сайту з кодуванням unicode-шляхів. */
    private function fetch(string $path): ?string
    {
        try {
            $resp = Http::withoutVerifying()->timeout(30)->get($this->base . $this->encodePath($path));
        } catch (\Throwable) {
            return null;
        }

        return $resp->successful() ? $resp->body() : null;
    }

    /** Кодує не-ASCII сегменти шляху (у старих URL трапляються «ä», «‑» тощо). */
    private function encodePath(string $path): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $path)));
    }

    /** Видаляє попередній імпорт: записи з маркером + завантажені фото. */
    private function purge(): void
    {
        $count = News::where('body', 'like', '%<!--imported-from:%')->delete();
        Storage::disk('public')->deleteDirectory($this->dir);
        $this->warn("Видалено попередній імпорт: {$count} новин + фото.");
    }
}
