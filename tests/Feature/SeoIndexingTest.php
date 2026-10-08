<?php

namespace Tests\Feature;

use App\Http\Controllers\SitemapController;
use App\Jobs\PostNewsToTelegram;
use App\Models\Department;
use App\Models\News;
use App\Models\NewsCategory;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Етап 1 docs/seo-plan.md: бар'єр індексації, canonical пагінації, sitemap,
 * стабільний lastmod, майбутні новини та корисна сторінка 404.
 */
class SeoIndexingTest extends TestCase
{
    use RefreshDatabase;

    private function news(array $attributes = []): News
    {
        return News::create($attributes + [
            'title' => 'SEO новина '.uniqid(),
            'body' => '<p>Текст</p>',
            'is_published' => true,
            'published_at' => now()->subDay(),
        ]);
    }

    // ── Бар'єр індексації ────────────────────────────────────────────────

    public function test_primary_domain_landing_pages_are_indexable(): void
    {
        config(['otfk.seo.indexing' => 'auto', 'otfk.seo.primary_host' => 'otfk.od.ua']);
        Page::updateOrCreate(['slug' => 'abituriyentu'], ['title' => 'Абітурієнту', 'is_published' => true]);

        foreach (['/', '/abituriyentu', '/spetsialnosti', '/novyny', '/kontakty'] as $path) {
            $response = $this->get('https://otfk.od.ua'.$path)->assertOk();
            $this->assertFalse($response->headers->has('X-Robots-Tag'), $path);
            $this->assertStringNotContainsString('noindex', $response->getContent(), $path);
            $response->assertSee('<link rel="canonical" href="https://otfk.od.ua'.rtrim($path, '/'), false);
        }

        $robots = $this->get('https://otfk.od.ua/robots.txt')->assertOk()->getContent();
        $this->assertDoesNotMatchRegularExpression('~^Disallow:\s*/\s*$~m', $robots);
        $this->assertStringContainsString('Sitemap: https://otfk.od.ua/sitemap.xml', $robots);
    }

    public function test_test_domain_is_closed_from_indexing_but_crawlable(): void
    {
        config(['otfk.seo.indexing' => 'auto', 'otfk.seo.primary_host' => 'otfk.od.ua']);

        $this->get('https://just-test.shop/')
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
            ->assertDontSee('rel="canonical"', false);

        // robots.txt не закриває обхід — інакше робот не побачить noindex.
        $this->get('https://just-test.shop/robots.txt')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertDontSee("Disallow: /\n", false);

        // Явне перевизначення оточення.
        config(['otfk.seo.indexing' => 'false']);
        $this->get('https://otfk.od.ua/')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_english_listing_is_indexable_and_admin_keeps_noindex_on_primary_domain(): void
    {
        config(['otfk.seo.indexing' => 'auto', 'otfk.seo.primary_host' => 'otfk.od.ua']);

        // Етап 4: /en індексується посторінково; деталі — EnglishIndexingTest.
        $this->get('https://otfk.od.ua/en')->assertOk()->assertHeaderMissing('X-Robots-Tag')
            ->assertSee('<link rel="canonical" href="https://otfk.od.ua/en">', false);
        $this->get('https://otfk.od.ua/en/poshuk?q=college')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, follow');
        $this->get('https://otfk.od.ua/admin/login')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_search_and_year_archive_are_noindex_follow(): void
    {
        $this->get('/poshuk?q=коледж')
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, follow">', false)
            ->assertDontSee('rel="canonical"', false);

        $this->news(['published_at' => now()->subYear()]);
        $this->get('/novyny?year='.now()->subYear()->year)
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, follow">', false);
    }

    // ── Canonical і пагінація ────────────────────────────────────────────

    public function test_paginated_news_canonical_points_to_itself_without_tracking_params(): void
    {
        foreach (range(1, 12) as $i) {
            $this->news();
        }

        $this->get('/novyny?page=2&utm_source=telegram&fbclid=abc')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.url('/novyny').'?page=2">', false)
            ->assertSee('<meta property="og:url" content="'.url('/novyny').'?page=2">', false);

        // Перша сторінка — без параметра page.
        $this->get('/novyny?page=1&utm_campaign=x')
            ->assertSee('<link rel="canonical" href="'.url('/novyny').'">', false);

        // Порожня сторінка за межами пагінації не віддається як 200-дубль.
        $this->get('/novyny?page=999')->assertNotFound();
    }

    public function test_news_category_is_self_canonical_and_unknown_category_is_404(): void
    {
        $category = NewsCategory::create(['title' => 'Оголошення SEO', 'slug' => 'oholoshennya-seo']);
        $this->news(['category_id' => $category->id]);

        $this->get('/novyny?category=oholoshennya-seo&utm_medium=x')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.url('/novyny').'?category=oholoshennya-seo">', false);

        $this->get('/novyny?category=nemaye-takoyi')->assertNotFound();
    }

    // ── Sitemap ──────────────────────────────────────────────────────────

    public function test_sitemap_lists_only_canonical_published_pages(): void
    {
        Department::create(['title' => 'Відділення SEO', 'slug' => 'viddilennya-seo', 'type' => 'viddilennya', 'is_published' => true]);
        Department::create(['title' => 'Чернетка SEO', 'slug' => 'chernetka-seo', 'type' => 'viddilennya', 'is_published' => false]);
        Page::create(['title' => 'Жива SEO', 'slug' => 'zhyva-seo', 'is_published' => true]);
        Page::create(['title' => 'Чернетка SEO', 'slug' => 'chernetka-storinka-seo', 'is_published' => false]);
        // Слаг, який перехоплює спеціальний маршрут /faq, не є адресою CMS-сторінки.
        $shadowed = Page::updateOrCreate(['slug' => 'faq'], ['title' => 'Тінь', 'is_published' => true]);
        $shadowed->forceFill(['updated_at' => '2001-01-01 00:00:00'])->saveQuietly();
        $this->news(['title' => 'Майбутня SEO', 'slug' => 'maybutnya-seo', 'published_at' => now()->addDay()]);

        $body = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString('<loc>'.url('/struktura/viddilennya-seo').'</loc>', $body);
        $this->assertStringContainsString('<loc>'.url('/zhyva-seo').'</loc>', $body);
        $this->assertStringNotContainsString('chernetka-seo', $body);
        $this->assertStringNotContainsString('chernetka-storinka-seo', $body);
        $this->assertStringNotContainsString('maybutnya-seo', $body);
        $this->assertStringNotContainsString('feed.xml', $body);
        $this->assertStringNotContainsString('2001-01-01', $body);
        // Неперекладені матеріали — без англійської адреси (EnglishIndexingTest).
        $this->assertStringNotContainsString(url('/en/zhyva-seo'), $body);
        $this->assertStringNotContainsString(url('/en/struktura/viddilennya-seo'), $body);
        $this->assertSame(1, substr_count($body, '<loc>'.url('/faq').'</loc>'));
    }

    public function test_sitemap_is_cached_and_flushed_when_content_is_saved(): void
    {
        $this->get('/sitemap.xml')->assertOk();
        $this->assertTrue(Cache::has(SitemapController::CACHE_KEY));

        $news = $this->news(['title' => 'Нова для карти', 'slug' => 'nova-dlya-karty']);
        $this->assertFalse(Cache::has(SitemapController::CACHE_KEY));

        $this->get('/sitemap.xml')->assertSee(url('/novyny/nova-dlya-karty'), false);

        // Лічильники не скидають кеш і не змінюють lastmod.
        $this->get('/novyny/nova-dlya-karty')->assertOk();
        $this->assertTrue(Cache::has(SitemapController::CACHE_KEY));

        $news->delete();
        $this->get('/sitemap.xml')->assertDontSee(url('/novyny/nova-dlya-karty'), false);
    }

    // ── lastmod і лічильники ─────────────────────────────────────────────

    public function test_views_and_likes_do_not_touch_updated_at_or_telegram(): void
    {
        Bus::fake([PostNewsToTelegram::class]);
        $news = $this->news(['slug' => 'lichylnyky-seo']);
        $news->forceFill(['updated_at' => '2025-03-04 05:06:07'])->saveQuietly();

        $this->get('/novyny/lichylnyky-seo')->assertOk();
        $this->postJson('/novyny/lichylnyky-seo/vpodobayka')->assertOk()->assertJson(['likes' => 1, 'liked' => true]);

        $fresh = $news->fresh();
        $this->assertSame(1, (int) $fresh->views);
        $this->assertSame(1, (int) $fresh->likes);
        $this->assertSame('2025-03-04 05:06:07', $fresh->updated_at->format('Y-m-d H:i:s'));

        // Повторний клік знімає лайк — теж без зміни дати.
        $this->postJson('/novyny/lichylnyky-seo/vpodobayka')->assertOk()->assertJson(['likes' => 0, 'liked' => false]);
        $this->assertSame('2025-03-04 05:06:07', $news->fresh()->updated_at->format('Y-m-d H:i:s'));
        $this->get('/sitemap.xml')->assertSee('<lastmod>2025-03-04</lastmod>', false);

        // Редакторська правка змінює дату.
        $this->travel(1)->minutes();
        $news->fresh()->update(['title' => 'Виправлена назва']);
        $this->assertNotSame('2025-03-04', $news->fresh()->updated_at->toDateString());

        Bus::assertNotDispatched(PostNewsToTelegram::class);
    }

    public function test_view_counter_on_page_reflects_increment(): void
    {
        $news = $this->news(['slug' => 'pokaznyk-seo', 'views' => 41]);

        $this->get('/novyny/pokaznyk-seo')->assertOk()->assertSee('42');
        $this->assertSame(42, (int) $news->fresh()->views);
    }

    // ── Майбутні новини ──────────────────────────────────────────────────

    public function test_future_news_is_hidden_from_guests_until_publication_date(): void
    {
        $news = $this->news(['slug' => 'zavtra-seo', 'published_at' => now()->addDay()]);

        $this->get('/novyny/zavtra-seo')->assertNotFound();
        $this->postJson('/novyny/zavtra-seo/vpodobayka')->assertNotFound();
        $this->assertSame(0, (int) $news->fresh()->views);

        $this->travel(2)->days();
        $this->get('/novyny/zavtra-seo')->assertOk();
    }

    // ── Корисна сторінка 404 ─────────────────────────────────────────────

    public function test_404_offers_admission_specialties_and_search_in_both_locales(): void
    {
        $this->get('/staryi/shlyakh.php')
            ->assertNotFound()
            ->assertSee('Після оновлення сайту частина адрес змінилася')
            ->assertSee('href="'.url('/abituriyentu').'"', false)
            ->assertSee('href="'.url('/spetsialnosti').'"', false)
            ->assertSee('href="'.url('/poshuk').'"', false);

        $this->get('/en/missing-page-seo')
            ->assertNotFound()
            ->assertSee('Some addresses changed')
            ->assertSee('href="'.url('/en/abituriyentu').'"', false)
            ->assertSee('href="'.url('/en/spetsialnosti').'"', false)
            ->assertSee('href="'.url('/en/poshuk').'"', false);
    }
}
