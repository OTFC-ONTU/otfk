<?php

namespace Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

/**
 * HTTP smoke-перевірка SEO розгорнутого сайту (otfk:seo-smoke): режими
 * indexable/closed і типові збої — noindex на основному домені, Disallow: /,
 * чужий canonical/sitemap, 5xx після повторів, 200 на невідомій адресі.
 */
class SeoSmokeTest extends TestCase
{
    private const BASE = 'https://otfk.od.ua';

    /**
     * Фейковий сайт: $overrides[path] замінює типову відповідь шляху,
     * $overrides['other'] обробляє інші схему/хост (перевірка http/www).
     *
     * @param  array<string, mixed>  $overrides
     */
    private function fakeSite(string $host, bool $closed, array $overrides = []): void
    {
        // Свіжа фабрика: повторний виклик у тесті замінює сайт, а не дописує заглушки.
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(function (Request $request) use ($host, $closed, $overrides) {
            if (! str_starts_with($request->url(), "https://{$host}/")) {
                return isset($overrides['other']) ? $overrides['other']($request) : Http::response('', 404);
            }

            $path = parse_url($request->url(), PHP_URL_PATH) ?: '/';
            if (array_key_exists($path, $overrides)) {
                $override = $overrides[$path];

                return is_callable($override) ? $override($request) : $override;
            }

            $headers = $closed ? ['X-Robots-Tag' => 'noindex, nofollow'] : [];
            $origin = "https://{$host}";

            return match (true) {
                $path === '/robots.txt' => Http::response("User-agent: *\nAllow: /\nDisallow: /admin\n\nSitemap: {$origin}/sitemap.xml\n", 200, $headers + ['Content-Type' => 'text/plain; charset=utf-8']),
                $path === '/sitemap.xml' => Http::response($this->sitemap([$origin, "{$origin}/novyny"]), 200, $headers + ['Content-Type' => 'application/xml']),
                str_starts_with($path, '/otfk-seo-smoke') => Http::response('Не знайдено', 404, $headers + ['Content-Type' => 'text/html; charset=utf-8']),
                default => Http::response($this->page($origin.rtrim($path, '/'), $closed), 200, $headers + ['Content-Type' => 'text/html; charset=utf-8']),
            };
        });
    }

    private function page(string $canonical, bool $closed): string
    {
        $robots = $closed ? '<meta name="robots" content="noindex, nofollow">' : '';
        $link = $closed ? '' : "<link rel=\"canonical\" href=\"{$canonical}\">";

        return "<!doctype html><html><head>{$robots}<meta property=\"og:url\" content=\"{$canonical}\">{$link}</head><body>ok</body></html>";
    }

    /** @param list<string> $locs */
    private function sitemap(array $locs): string
    {
        $urls = implode('', array_map(fn ($loc) => "<url><loc>{$loc}</loc></url>", $locs));

        return '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.$urls.'</urlset>';
    }

    private function html(string $canonical): array
    {
        return [$this->page($canonical, false), 200, ['Content-Type' => 'text/html']];
    }

    private function smoke(array $options = []): PendingCommand
    {
        return $this->artisan('otfk:seo-smoke', $options + ['--base' => self::BASE, '--expect' => 'indexable', '--retry-delay' => 0]);
    }

    public function test_indexable_primary_domain_passes(): void
    {
        $this->fakeSite('otfk.od.ua', closed: false);

        $this->smoke()->expectsOutputToContain('Smoke-перевірку пройдено')->assertSuccessful();

        // Ключові адреси, robots, sitemap і невідома адреса запитані без переходу за редиректами.
        foreach (['/', '/abituriyentu', '/spetsialnosti', '/novyny', '/kontakty', '/sitemap.xml', '/robots.txt', '/otfk-seo-smoke-perevirka-404'] as $path) {
            Http::assertSent(fn (Request $r) => parse_url($r->url(), PHP_URL_PATH) === $path);
        }
        Http::assertSent(fn (Request $r) => $r->header('User-Agent') === ['otfk-seo-smoke/1.0']);
    }

    public function test_closed_test_domain_passes_and_requires_noindex(): void
    {
        $this->fakeSite('just-test.shop', closed: true);
        $this->smoke(['--base' => 'https://just-test.shop', '--expect' => 'closed'])->assertSuccessful();

        // Той самий тестовий домен без noindex — провал.
        $this->fakeSite('just-test.shop', closed: false);
        $this->smoke(['--base' => 'https://just-test.shop', '--expect' => 'closed'])
            ->expectsOutputToContain('ПОМИЛКА')
            ->assertFailed();
    }

    public function test_noindex_on_indexable_domain_fails(): void
    {
        $this->fakeSite('otfk.od.ua', closed: false, overrides: [
            '/novyny' => Http::response($this->page(self::BASE.'/novyny', false), 200, ['Content-Type' => 'text/html', 'X-Robots-Tag' => 'noindex, nofollow']),
        ]);
        $this->smoke()->assertFailed();

        // meta robots з довільним порядком атрибутів.
        $this->fakeSite('otfk.od.ua', closed: false, overrides: [
            '/kontakty' => Http::response('<html><head><meta content="noindex" name="robots"><link rel="canonical" href="https://otfk.od.ua/kontakty"><meta property="og:url" content="https://otfk.od.ua/kontakty"></head></html>', 200, ['Content-Type' => 'text/html']),
        ]);
        $this->smoke()->assertFailed();
    }

    public function test_robots_disallow_root_fails_in_both_modes(): void
    {
        foreach (["User-agent: *\nDisallow: /\n", "User-agent: Googlebot\nDisallow: /\n\nUser-agent: *\nAllow: /\nSitemap: https://otfk.od.ua/sitemap.xml\n"] as $robots) {
            $this->fakeSite('otfk.od.ua', closed: false, overrides: ['/robots.txt' => Http::response($robots, 200, ['Content-Type' => 'text/plain'])]);
            $this->smoke()->expectsOutputToContain('Disallow: / для')->assertFailed();
        }

        // На тестовому домені закритий обхід теж помилка: робот не побачить noindex.
        $this->fakeSite('just-test.shop', closed: true, overrides: [
            '/robots.txt' => Http::response("User-agent: *\nDisallow: /\n", 200, ['Content-Type' => 'text/plain', 'X-Robots-Tag' => 'noindex']),
        ]);
        $this->smoke(['--base' => 'https://just-test.shop', '--expect' => 'closed'])->assertFailed();

        // Disallow для іншого агента або часткового шляху допустимий.
        $this->fakeSite('otfk.od.ua', closed: false, overrides: [
            '/robots.txt' => Http::response("User-agent: BadBot\nDisallow: /\n\nUser-agent: *\nDisallow: /admin\nSitemap: https://otfk.od.ua/sitemap.xml\n", 200, ['Content-Type' => 'text/plain']),
        ]);
        $this->smoke()->assertSuccessful();
    }

    public function test_foreign_canonical_or_og_url_fails(): void
    {
        $this->fakeSite('otfk.od.ua', closed: false, overrides: [
            '/novyny' => Http::response(...$this->html('https://just-test.shop/novyny')),
        ]);
        $this->smoke()->expectsOutputToContain('https://just-test.shop/novyny')->assertFailed();

        // http-canonical на https-базі — теж помилка (схема неправильно визначена за проксі).
        $this->fakeSite('otfk.od.ua', closed: false, overrides: [
            '/' => Http::response(...$this->html('http://otfk.od.ua')),
        ]);
        $this->smoke()->assertFailed();
    }

    public function test_sitemap_with_foreign_host_invalid_or_empty_fails(): void
    {
        foreach ([
            $this->sitemap(['https://otfk.od.ua', 'https://just-test.shop/novyny']),
            '<urlset><url><loc>https://otfk.od.ua</loc></url>',
            $this->sitemap([]),
        ] as $body) {
            $this->fakeSite('otfk.od.ua', closed: false, overrides: ['/sitemap.xml' => Http::response($body, 200, ['Content-Type' => 'application/xml'])]);
            $this->smoke()->assertFailed();
        }
    }

    public function test_server_errors_are_retried_then_reported(): void
    {
        $attempts = 0;
        $this->fakeSite('otfk.od.ua', closed: false, overrides: [
            '/novyny' => function () use (&$attempts) {
                $attempts++;

                return Http::response('Bad gateway', 502);
            },
        ]);
        $this->smoke()->expectsOutputToContain('502')->assertFailed();
        $this->assertSame(3, $attempts);

        // Тимчасовий збій, що минає з другої спроби, перевірку не валить.
        $attempts = 0;
        $this->fakeSite('otfk.od.ua', closed: false, overrides: [
            '/novyny' => function () use (&$attempts) {
                if (++$attempts === 1) {
                    throw new ConnectionException('timeout');
                }

                return Http::response(...$this->html(self::BASE.'/novyny'));
            },
        ]);
        $this->smoke()->assertSuccessful();
        $this->assertSame(2, $attempts);
    }

    public function test_unknown_path_must_return_404(): void
    {
        $this->fakeSite('otfk.od.ua', closed: false, overrides: [
            '/otfk-seo-smoke-perevirka-404' => Http::response(...$this->html(self::BASE)),
        ]);
        $this->smoke()->assertFailed();

        $this->fakeSite('otfk.od.ua', closed: false, overrides: [
            '/otfk-seo-smoke-perevirka-404' => Http::response('', 302, ['Location' => self::BASE.'/']),
        ]);
        $this->smoke()->expectsOutputToContain('302')->assertFailed();
    }

    public function test_key_page_redirect_fails_and_paths_can_be_overridden(): void
    {
        $this->fakeSite('otfk.od.ua', closed: false, overrides: [
            '/abituriyentu' => Http::response('', 301, ['Location' => self::BASE.'/']),
        ]);
        $this->smoke()->assertFailed();

        // Лише власний перелік: зламана /abituriyentu більше не перевіряється.
        $this->smoke(['--only-paths' => true, '--path' => ['/novyny', 'robots.txt']])->assertSuccessful();
    }

    public function test_redirect_normalization_check(): void
    {
        $good = [
            'http://otfk.od.ua/' => [301, 'https://otfk.od.ua/'],
            'http://www.otfk.od.ua/' => [301, 'https://otfk.od.ua/'],
            'https://www.otfk.od.ua/novyny?smoke=1' => [301, 'https://otfk.od.ua/novyny?smoke=1'],
        ];
        $this->fakeSite('otfk.od.ua', closed: false, overrides: [
            'other' => fn (Request $r) => Http::response('', $good[$r->url()][0], ['Location' => $good[$r->url()][1]]),
        ]);
        $this->smoke(['--check-redirects' => true])->assertSuccessful();

        // Ланцюжок через www або 302 замість 301 — помилка.
        $bad = $good;
        $bad['http://www.otfk.od.ua/'] = [301, 'https://www.otfk.od.ua/'];
        $bad['https://www.otfk.od.ua/novyny?smoke=1'] = [302, 'https://otfk.od.ua/novyny?smoke=1'];
        $this->fakeSite('otfk.od.ua', closed: false, overrides: [
            'other' => fn (Request $r) => Http::response('', $bad[$r->url()][0], ['Location' => $bad[$r->url()][1]]),
        ]);
        $this->smoke(['--check-redirects' => true])->assertFailed();
    }

    public function test_htaccess_normalizes_only_primary_host(): void
    {
        $htaccess = file_get_contents(public_path('.htaccess'));

        // www → без www і HTTP → HTTPS, кожне правило обмежене хостом otfk.od.ua.
        $this->assertMatchesRegularExpression('~RewriteCond %\{HTTP_HOST\} \^www\\\\\.otfk\\\\\.od\\\\\.ua[^\n]*\[NC\]\n\s*RewriteRule \^ https://otfk\.od\.ua%\{REQUEST_URI\} \[L,R=301\]~', $htaccess);
        $this->assertMatchesRegularExpression('~RewriteCond %\{HTTP_HOST\} \^otfk\\\\\.od\\\\\.ua[^\n]*\[NC\]\n\s*RewriteCond %\{HTTPS\} !=on\n\s*RewriteCond %\{HTTP:X-Forwarded-Proto\} !\^https\$ \[NC\]\n[^\n]*X-Forwarded-SSL[^\n]*\n\s*RewriteRule \^ https://otfk\.od\.ua%\{REQUEST_URI\} \[L,R=301\]~', $htaccess);
        $this->assertSame(2, substr_count($htaccess, 'RewriteRule ^ https://'), 'Інших редиректів на https без умови хоста немає');

        // Порядок: захист /storage першим, нормалізація до прибирання слешу.
        $storage = strpos($htaccess, 'RewriteRule ^storage/');
        $www = strpos($htaccess, 'RewriteCond %{HTTP_HOST} ^www');
        $slash = strpos($htaccess, 'RewriteCond %{REQUEST_URI} (.+)/$');
        $this->assertTrue($storage < $www && $www < $slash);
    }

    public function test_english_paths_and_reciprocal_hreflang_are_checked(): void
    {
        $set = fn (string $en) => '<link rel="alternate" hreflang="uk" href="'.self::BASE.'"><link rel="alternate" hreflang="en" href="'.$en.'">'
            .'<link rel="alternate" hreflang="x-default" href="'.self::BASE.'">';
        $page = fn (string $canonical, string $links) => Http::response(
            "<html><head><link rel=\"canonical\" href=\"{$canonical}\"><meta property=\"og:url\" content=\"{$canonical}\">{$links}</head></html>",
            200, ['Content-Type' => 'text/html'],
        );

        $this->fakeSite('otfk.od.ua', closed: false, overrides: [
            '/' => $page(self::BASE, $set(self::BASE.'/en')),
            '/en' => $page(self::BASE.'/en', $set(self::BASE.'/en')),
        ]);
        $this->smoke()->expectsOutputToContain('hreflang взаємний')->assertSuccessful();
        foreach (['/en', '/en/spetsialnosti'] as $path) {
            Http::assertSent(fn (Request $r) => parse_url($r->url(), PHP_URL_PATH) === $path);
        }

        // Англійська версія не посилається назад — провал.
        $this->fakeSite('otfk.od.ua', closed: false, overrides: [
            '/' => $page(self::BASE, $set(self::BASE.'/en')),
            '/en' => $page(self::BASE.'/en', ''),
        ]);
        $this->smoke()->assertFailed();

        // Англійська сторінка з noindex на основному домені — провал.
        $this->fakeSite('otfk.od.ua', closed: false, overrides: [
            '/en/spetsialnosti' => Http::response($this->page(self::BASE.'/en/spetsialnosti', false), 200, ['Content-Type' => 'text/html', 'X-Robots-Tag' => 'noindex, follow']),
        ]);
        $this->smoke()->assertFailed();
    }

    public function test_invalid_options_are_rejected(): void
    {
        Http::preventStrayRequests();

        $this->artisan('otfk:seo-smoke')->assertFailed();
        $this->artisan('otfk:seo-smoke', ['--base' => 'otfk.od.ua'])->assertFailed();
        $this->artisan('otfk:seo-smoke', ['--base' => self::BASE, '--expect' => 'maybe'])->assertFailed();
        $this->artisan('otfk:seo-smoke', ['--base' => self::BASE, '--resolve' => 'otfk.od.ua'])->assertFailed();
    }
}
