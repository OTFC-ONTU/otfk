<?php

namespace App\Console\Commands;

use DOMDocument;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * HTTP smoke-перевірка SEO на справжньому розгорнутому сайті (docs/seo-plan.md,
 * етап 1, «Автоматичний бар'єр індексації»).
 *
 * indexable — цільовий основний домен: ключові сторінки 200 без noindex
 * (заголовок і meta), canonical/og:url і <loc> карти сайту — на хості бази,
 * robots.txt не закриває корінь для * / Googlebot / Bingbot; англійські розділи
 * (ENGLISH_PATHS) теж 200 без noindex, а hreflang головної — взаємний.
 * closed — тестовий хостинг: кожна відповідь несе noindex, але robots.txt
 * обхід не закриває (інакше робот не побачить директиву).
 * В обох режимах невідома адреса має віддавати саме 404, а не 200 чи
 * редирект на головну. Мережеві збої та 5xx повторюються з паузою.
 */
class SeoSmoke extends Command
{
    /** Ключові адреси за замовчуванням. */
    public const DEFAULT_PATHS = ['/', '/abituriyentu', '/spetsialnosti', '/novyny', '/kontakty', '/sitemap.xml', '/robots.txt'];

    /** Англійські розділи, відкриті для індексації (етап 4); перевіряються в режимі indexable. */
    public const ENGLISH_PATHS = ['/en', '/en/spetsialnosti'];

    /** Свідомо неіснуюча адреса; службовий параметр відкидається журналом 404, тож рядок у журналі один. */
    public const UNKNOWN_PATH = '/otfk-seo-smoke-perevirka-404';

    /** Агенти, для яких robots.txt не має закривати корінь. */
    private const AGENTS = ['*', 'googlebot', 'bingbot'];

    protected $signature = 'otfk:seo-smoke
        {--base= : Базова адреса сайту, напр. https://otfk.od.ua (обовʼязково)}
        {--expect=closed : indexable для основного домену або closed для тестового}
        {--path=* : Додаткові ключові шляхи; можна повторювати}
        {--only-paths : Перевіряти лише шляхи з --path, без типового переліку}
        {--host-header= : Заголовок Host для бази-IP до перемикання DNS, лише для http}
        {--resolve= : Підміна DNS у форматі host:port:ip, як у curl, для https до перемикання DNS}
        {--check-redirects : Також перевірити 301 з http і www на базову адресу}
        {--sitemap-sample=0 : Скільки перших адрес з sitemap перевірити на 200}
        {--timeout=15 : Тайм-аут одного запиту, секунд}
        {--retries=3 : Кількість спроб для мережевих збоїв і 5xx}
        {--retry-delay=2000 : Базова пауза між спробами, мс; зростає з кожною спробою}';

    protected $description = 'HTTP smoke-перевірка SEO розгорнутого сайту: статуси, noindex, canonical, robots.txt, sitemap, 404';

    /** @var list<array{0: string, 1: string, 2: string, 3: string}> */
    private array $rows = [];

    private bool $failed = false;

    private string $base;

    private string $scheme;

    /** Хост, який має фігурувати в canonical/og:url/sitemap. */
    private string $host;

    public function handle(): int
    {
        // Екземпляр команди може використовуватися повторно в одному процесі.
        $this->rows = [];
        $this->failed = false;

        $base = rtrim(trim((string) $this->option('base')), '/');
        $parts = parse_url($base);
        if ($base === '' || ! isset($parts['scheme'], $parts['host']) || ! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            $this->error('Вкажіть --base з повною адресою, напр. --base=https://otfk.od.ua');

            return self::FAILURE;
        }
        if (isset($parts['path']) && $parts['path'] !== '') {
            $this->error('--base має бути коренем сайту без шляху.');

            return self::FAILURE;
        }

        $expect = strtolower((string) $this->option('expect'));
        if (! in_array($expect, ['indexable', 'closed'], true)) {
            $this->error('--expect приймає лише indexable або closed.');

            return self::FAILURE;
        }

        $resolve = (string) $this->option('resolve');
        if ($resolve !== '' && ! preg_match('/^[^:\s]+:\d+:[0-9a-fA-F.:\[\]]+$/', $resolve)) {
            $this->error('--resolve має формат host:port:ip, напр. otfk.od.ua:443:203.0.113.10');

            return self::FAILURE;
        }

        $this->base = $base;
        $this->scheme = strtolower($parts['scheme']);
        $hostHeader = strtolower(trim((string) $this->option('host-header')));
        $this->host = $hostHeader !== '' ? $hostHeader : strtolower($parts['host']);

        $paths = $this->option('only-paths') ? [] : self::DEFAULT_PATHS;
        if ($expect === 'indexable' && ! $this->option('only-paths')) {
            $paths = array_merge($paths, self::ENGLISH_PATHS);
        }
        foreach ((array) $this->option('path') as $path) {
            $path = '/'.ltrim(trim((string) $path), '/');
            if (! in_array($path, $paths, true)) {
                $paths[] = $path;
            }
        }
        if ($paths === []) {
            $this->error('Немає жодного шляху для перевірки.');

            return self::FAILURE;
        }

        $this->line("Перевірка {$base} (очікування: {$expect}, хост: {$this->host})");

        foreach ($paths as $path) {
            $this->checkPath($path, $expect);
        }
        if ($expect === 'indexable' && in_array('/', $paths, true)) {
            $this->checkHreflang();
        }
        $this->checkUnknownPath();
        if ($this->option('check-redirects')) {
            $this->checkRedirects();
        }

        $this->table(['Перевірка', 'Адреса', 'Результат', 'Деталі'], $this->rows);

        if ($this->failed) {
            $this->error('Smoke-перевірку не пройдено.');

            return self::FAILURE;
        }
        $this->info('Smoke-перевірку пройдено.');

        return self::SUCCESS;
    }

    // ── Перевірки ────────────────────────────────────────────────────────

    private function checkPath(string $path, string $expect): void
    {
        $response = $this->fetch($path);
        if (! $response instanceof Response) {
            $this->result('Статус 200', $path, false, $response);

            return;
        }

        $status = $response->status();
        $location = $response->header('Location');
        $this->result('Статус 200', $path, $status === 200, $status === 200 ? '200' : "{$status}".($location ? " → {$location}" : ''));
        if ($status !== 200) {
            return;
        }

        $isHtml = str_contains(strtolower($response->header('Content-Type')), 'text/html');
        $headerRobots = strtolower($response->header('X-Robots-Tag'));
        $metaRobots = $isHtml ? strtolower((string) $this->metaContent($response->body(), 'name', 'robots')) : '';

        if ($expect === 'indexable') {
            $this->result('Без noindex у X-Robots-Tag', $path, ! str_contains($headerRobots, 'noindex'), $headerRobots ?: 'немає');
            if ($isHtml) {
                $this->result('Без noindex у meta robots', $path, ! str_contains($metaRobots, 'noindex'), $metaRobots ?: 'немає');
                $this->checkHtmlUrl('Canonical на базовому хості', $path, $this->linkHref($response->body(), 'canonical'));
                $this->checkHtmlUrl('og:url на базовому хості', $path, $this->metaContent($response->body(), 'property', 'og:url'));
            }
        } else {
            $closed = str_contains($headerRobots, 'noindex') || str_contains($metaRobots, 'noindex');
            $this->result('Є noindex', $path, $closed, trim('заголовок: '.($headerRobots ?: 'немає').($isHtml ? '; meta: '.($metaRobots ?: 'немає') : '')));
        }

        if ($path === '/robots.txt') {
            $this->checkRobots($response->body(), $expect);
        }
        if ($path === '/sitemap.xml') {
            $this->checkSitemap($response->body());
        }
    }

    private function checkRobots(string $body, string $expect): void
    {
        $blocked = [];
        $sitemaps = [];
        $agents = [];
        $inRules = false;

        foreach (preg_split('/\R/', $body) as $line) {
            $line = trim((string) preg_replace('/#.*$/', '', $line));
            if (! str_contains($line, ':')) {
                continue;
            }
            [$field, $value] = array_map('trim', explode(':', $line, 2));
            $field = strtolower($field);

            if ($field === 'user-agent') {
                // Нова група починається з User-agent після правил попередньої.
                if ($inRules) {
                    $agents = [];
                    $inRules = false;
                }
                $agents[] = strtolower($value);
            } elseif ($field === 'sitemap') {
                $sitemaps[] = $value;
            } elseif (in_array($field, ['disallow', 'allow'], true)) {
                $inRules = true;
                if ($field === 'disallow' && in_array($value, ['/', '/*'], true)) {
                    foreach (array_intersect($agents, self::AGENTS) as $agent) {
                        $blocked[] = $agent;
                    }
                }
            }
        }

        $blocked = array_values(array_unique($blocked));
        $this->result('robots.txt не закриває корінь', '/robots.txt', $blocked === [], $blocked === [] ? 'для *, Googlebot, Bingbot відкрито' : 'Disallow: / для '.implode(', ', $blocked));

        if ($expect === 'indexable') {
            $foreign = array_filter($sitemaps, fn ($url) => ! $this->ownUrl($url));
            $ok = $sitemaps !== [] && $foreign === [];
            $this->result('robots.txt: Sitemap на базовому хості', '/robots.txt', $ok, $sitemaps === [] ? 'рядка Sitemap немає' : ($foreign === [] ? $sitemaps[0] : 'чужа адреса: '.reset($foreign)));
        }
    }

    private function checkSitemap(string $body): void
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument;
        $valid = trim($body) !== '' && $dom->loadXML($body, LIBXML_NONET);
        $error = libxml_get_last_error();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $valid ? $dom->documentElement?->localName : null;
        if (! $valid || ! in_array($root, ['urlset', 'sitemapindex'], true)) {
            $this->result('sitemap.xml — валідний XML', '/sitemap.xml', false, $valid ? "корінь {$root}" : 'помилка XML'.($error ? ': '.trim($error->message) : ''));

            return;
        }
        $this->result('sitemap.xml — валідний XML', '/sitemap.xml', true, $root);

        $locs = [];
        foreach ($dom->getElementsByTagNameNS('*', 'loc') as $node) {
            $locs[] = trim($node->textContent);
        }
        $this->result('sitemap.xml не порожній', '/sitemap.xml', $locs !== [], count($locs).' адрес');
        if ($locs === []) {
            return;
        }

        $foreign = array_values(array_filter($locs, fn ($url) => ! $this->ownUrl($url)));
        $this->result('sitemap.xml без чужих хостів', '/sitemap.xml', $foreign === [], $foreign === [] ? "усі на {$this->host}" : count($foreign).', напр. '.$foreign[0]);

        $sample = max(0, (int) $this->option('sitemap-sample'));
        foreach (array_slice($locs, 0, $sample) as $url) {
            if (! $this->ownUrl($url)) {
                continue;
            }
            $path = (parse_url($url, PHP_URL_PATH) ?: '/').(($q = parse_url($url, PHP_URL_QUERY)) ? "?{$q}" : '');
            $response = $this->fetch($path);
            $ok = $response instanceof Response && $response->status() === 200;
            $this->result('Адреса з sitemap — 200', $path, $ok, $response instanceof Response ? (string) $response->status() : $response);
        }
    }

    private function checkUnknownPath(): void
    {
        $response = $this->fetch(self::UNKNOWN_PATH.'?_smoke='.bin2hex(random_bytes(4)));
        if (! $response instanceof Response) {
            $this->result('Невідома адреса — 404', self::UNKNOWN_PATH, false, $response);

            return;
        }
        $status = $response->status();
        $location = $response->header('Location');
        $this->result('Невідома адреса — 404', self::UNKNOWN_PATH, $status === 404, (string) $status.($location ? " → {$location}" : ''));
    }

    /** 301 з http:// та www. на базову адресу (лише для https-бази без підміни Host). */
    private function checkRedirects(): void
    {
        if ($this->scheme !== 'https' || $this->option('host-header')) {
            $this->result('Нормалізація http/www', $this->base, false, 'потрібна https-база без --host-header');

            return;
        }

        $cases = [['http://'.$this->host.'/', '/']];
        if (! str_starts_with($this->host, 'www.')) {
            $cases[] = ['https://www.'.$this->host.'/novyny?smoke=1', '/novyny?smoke=1'];
            $cases[] = ['http://www.'.$this->host.'/', '/'];
        }

        foreach ($cases as [$url, $path]) {
            $response = $this->fetch($url, absolute: true);
            if (! $response instanceof Response) {
                $this->result('301 на базову адресу', $url, false, $response);

                continue;
            }
            $location = rtrim($response->header('Location'), '/');
            $expected = rtrim($this->base.$path, '/');
            $ok = $response->status() === 301 && $location === $expected;
            $this->result('301 на базову адресу', $url, $ok, $response->status().($location !== '' ? " → {$location}" : ''));
        }
    }

    private function checkHtmlUrl(string $title, string $path, ?string $url): void
    {
        if ($url === null || $url === '') {
            $this->result($title, $path, false, 'відсутній');

            return;
        }
        $this->result($title, $path, $this->ownUrl($url), $url);
    }

    // ── Допоміжне ────────────────────────────────────────────────────────

    /**
     * GET без переходу за редиректами; мережеві збої та 5xx повторюються.
     * Повертає відповідь або текст помилки.
     */
    private function fetch(string $pathOrUrl, bool $absolute = false): Response|string
    {
        $url = $absolute ? $pathOrUrl : $this->base.$pathOrUrl;
        $tries = max(1, (int) $this->option('retries'));
        $delay = max(0, (int) $this->option('retry-delay'));

        try {
            return $this->client()
                ->retry(
                    $tries,
                    fn (int $attempt) => $delay * $attempt,
                    // Для 3xx Laravel передає null замість винятку — такі відповіді не повторюємо.
                    fn (?Throwable $e) => $e instanceof ConnectionException
                        || ($e instanceof RequestException && $e->response->serverError()),
                    throw: false,
                )
                ->get($url);
        } catch (ConnectionException $e) {
            return "мережева помилка після {$tries} спроб: ".$e->getMessage();
        } catch (Throwable $e) {
            return 'помилка: '.$e->getMessage();
        }
    }

    private function client(): PendingRequest
    {
        $client = Http::withoutRedirecting()
            ->timeout(max(1, (int) $this->option('timeout')))
            ->connectTimeout(10)
            ->withUserAgent('otfk-seo-smoke/1.0')
            ->accept('text/html,application/xml,text/plain;q=0.9,*/*;q=0.8');

        if ($hostHeader = trim((string) $this->option('host-header'))) {
            $client = $client->withHeaders(['Host' => $hostHeader]);
        }
        if ($resolve = trim((string) $this->option('resolve'))) {
            $client = $client->withOptions(['curl' => [CURLOPT_RESOLVE => [$resolve]]]);
        }

        return $client;
    }

    /** Адреса належить базовому хосту (і схемі бази, якщо Host не підмінено). */
    private function ownUrl(string $url): bool
    {
        $parts = parse_url(trim($url));
        if (! isset($parts['host'], $parts['scheme'])) {
            return false;
        }
        if (strtolower($parts['host']) !== $this->host) {
            return false;
        }

        return $this->option('host-header') ? true : strtolower($parts['scheme']) === $this->scheme;
    }

    private function metaContent(string $html, string $attribute, string $value): ?string
    {
        foreach ($this->tags($html, 'meta') as $attrs) {
            if (strtolower($attrs[$attribute] ?? '') === $value) {
                return html_entity_decode($attrs['content'] ?? '', ENT_QUOTES | ENT_HTML5);
            }
        }

        return null;
    }

    /**
     * hreflang головної взаємний: англійська версія з набору посилається назад
     * тим самим набором uk/en/x-default. Без hreflang на головній перевірка
     * пропускається (англійська версія може бути ще не відкрита).
     */
    private function checkHreflang(): void
    {
        $home = $this->fetch('/');
        if (! $home instanceof Response || $home->status() !== 200) {
            return; // Статус головної вже звітовано.
        }

        $set = $this->alternates($home->body());
        if ($set === []) {
            $this->result('hreflang взаємний', '/', true, 'hreflang на / немає — пропущено');

            return;
        }

        $complete = isset($set['uk'], $set['en'], $set['x-default'])
            && $this->ownUrl($set['uk']) && $this->ownUrl($set['en']) && $this->ownUrl($set['x-default']);
        if (! $complete) {
            $this->result('hreflang взаємний', '/', false, 'неповний набір uk/en/x-default або чужий хост: '.implode(', ', array_keys($set)));

            return;
        }

        $path = (parse_url($set['en'], PHP_URL_PATH) ?: '/').(($q = parse_url($set['en'], PHP_URL_QUERY)) ? "?{$q}" : '');
        $english = $this->fetch($path);
        if (! $english instanceof Response || $english->status() !== 200) {
            $this->result('hreflang взаємний', $path, false, $english instanceof Response ? (string) $english->status() : $english);

            return;
        }

        $back = $this->alternates($english->body());
        $ok = ($back['uk'] ?? null) === $set['uk'] && ($back['en'] ?? null) === $set['en'] && ($back['x-default'] ?? null) === $set['x-default'];
        $this->result('hreflang взаємний', $path, $ok, $ok ? "uk ↔ {$set['en']}" : 'англійська версія не повторює набір головної');
    }

    /** @return array<string, string> hreflang => href з <link rel="alternate" hreflang> */
    private function alternates(string $html): array
    {
        $result = [];
        foreach ($this->tags($html, 'link') as $attrs) {
            if (isset($attrs['hreflang']) && in_array('alternate', preg_split('/\s+/', strtolower($attrs['rel'] ?? '')), true)) {
                $result[strtolower($attrs['hreflang'])] = html_entity_decode($attrs['href'] ?? '', ENT_QUOTES | ENT_HTML5);
            }
        }

        return $result;
    }

    private function linkHref(string $html, string $rel): ?string
    {
        foreach ($this->tags($html, 'link') as $attrs) {
            if (in_array($rel, preg_split('/\s+/', strtolower($attrs['rel'] ?? '')), true)) {
                return html_entity_decode($attrs['href'] ?? '', ENT_QUOTES | ENT_HTML5);
            }
        }

        return null;
    }

    /** @return list<array<string, string>> атрибути всіх тегів $tag у <head> (або всьому документі). */
    private function tags(string $html, string $tag): array
    {
        $head = preg_match('~<head\b.*?</head>~is', $html, $m) ? $m[0] : $html;
        preg_match_all('~<'.$tag.'\b([^>]*)>~i', $head, $matches);

        $result = [];
        foreach ($matches[1] as $raw) {
            preg_match_all('~([\w:-]+)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))~', $raw, $pairs, PREG_SET_ORDER);
            $attrs = [];
            foreach ($pairs as $pair) {
                $attrs[strtolower($pair[1])] = $pair[2] !== '' ? $pair[2] : (($pair[3] ?? '') !== '' ? $pair[3] : ($pair[4] ?? ''));
            }
            $result[] = $attrs;
        }

        return $result;
    }

    private function result(string $check, string $path, bool $ok, string $details): void
    {
        $this->rows[] = [$check, $path, $ok ? 'OK' : 'ПОМИЛКА', $details];
        $this->failed = $this->failed || ! $ok;
    }
}
