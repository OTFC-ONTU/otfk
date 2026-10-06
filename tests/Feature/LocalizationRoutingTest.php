<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\News;
use App\Models\Page;
use App\Models\SiteVisit;
use App\Support\LocalizedUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LocalizationRoutingTest extends TestCase
{
    use RefreshDatabase;

    public static function englishPages(): array
    {
        return array_map(fn ($path) => [$path], [
            '/en', '/en/novyny', '/en/video', '/en/dokumenty',
            '/en/spetsialnosti', '/en/struktura', '/en/administratsiya',
            '/en/halereya', '/en/poshuk?q=коледж', '/en/kontakty',
            '/en/abituriyentu', '/en/faq', '/en/kviz', '/en/podiyi',
            '/en/rozklad-dzvinkiv', '/en/novyny/feed.xml',
        ]);
    }

    #[DataProvider('englishPages')]
    public function test_english_routes_respond_without_being_indexed(string $path): void
    {
        $this->get($path)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, follow');
        $this->assertSame('en', app()->getLocale());
    }

    public function test_original_materials_and_shared_views_are_preserved(): void
    {
        $news = News::published()->firstOrFail();
        $views = $news->views;
        $this->get('/en/novyny/'.$news->slug)->assertOk()->assertSee($news->title);
        $this->get('/novyny/'.$news->slug)->assertOk()->assertSee($news->title);
        $this->assertSame($views + 1, $news->fresh()->views);

        $page = Page::where('slug', 'abituriyentu')->firstOrFail();
        $this->get('/en/'.$page->slug)->assertOk()->assertSee($page->title);
    }

    public function test_locale_is_reset_for_ukrainian_pages_and_admin(): void
    {
        $this->get('/en/faq')->assertOk();
        $this->get('/faq')->assertOk()->assertHeaderMissing('X-Robots-Tag');
        $this->assertSame('uk', app()->getLocale());
        $this->get('/en/faq')->assertOk();
        $this->get('/admin/login')->assertOk();
        $this->assertSame('uk', app()->getLocale());
        $this->get('/en/admin')->assertNotFound();
        $this->get('/en/livewire')->assertNotFound();
        $this->get('/en/missing-page')->assertNotFound();
    }

    public function test_localized_urls_preserve_queries_fragments_and_excluded_links(): void
    {
        $this->assertSame(url('/en/novyny?year=2026&category=test#list'), LocalizedUrl::to('/novyny?year=2026&category=test#list', 'en'));
        $this->assertSame(url('/novyny?year=2026#list'), LocalizedUrl::to(url('/en/novyny?year=2026#list'), 'uk'));
        $this->assertSame(url('/en'), LocalizedUrl::to('/', 'en'));
        $this->assertSame(url('/'), LocalizedUrl::to('/en', 'uk'));
        $this->assertSame(route('en.news.index'), LocalizedUrl::route('news.index', [], 'en'));
        $this->assertSame(route('news.index'), LocalizedUrl::route('en.news.index', [], 'uk'));
        $this->assertSame(route('sitemap'), LocalizedUrl::route('sitemap', [], 'en'));

        foreach (['https://example.org/novyny', '//example.org/novyny', '/admin/login', '/storage/file.pdf', '/build/assets/app.js', '/sitemap.xml', '/robots.txt', '/up', '#main', 'mailto:info@example.org', 'tel:+380000000000'] as $url) {
            $this->assertSame($url, LocalizedUrl::to($url, 'en'));
        }

        $this->get('/en/poshuk?q=college&page=2')->assertOk();
        $this->assertSame(url('/poshuk?page=2&q=college'), LocalizedUrl::current('uk'));
    }

    public function test_cached_navigation_resolves_links_for_each_request_language(): void
    {
        $this->get('/en/faq')->assertOk();
        $menu = MenuItem::navigation();
        $item = $menu->first(fn ($item) => $item->page !== null);
        $this->assertNotNull($item);
        $this->assertSame(url('/en/'.$item->page->slug), $item->href);
        $this->get('/faq')->assertOk();
        $this->assertSame(url('/'.$item->page->slug), $item->href);
    }

    public function test_english_posts_use_the_existing_handlers_and_shared_likes(): void
    {
        $news = News::published()->firstOrFail();
        $this->postJson('/en/novyny/'.$news->slug.'/vpodobayka')->assertOk()->assertJson(['liked' => true]);
        $this->postJson('/novyny/'.$news->slug.'/vpodobayka')->assertOk()->assertJson(['liked' => false]);
        $this->assertSame(0, $news->likeRecords()->count());
        $this->postJson('/en/kontakty', [])->assertStatus(405);
        $this->postJson('/en/zayavka', [])->assertStatus(405);
        $this->assertDatabaseCount('feedback_messages', 0);
        $this->assertDatabaseCount('applicant_requests', 0);
    }

    public function test_english_search_suggestions_do_not_count_as_page_visits(): void
    {
        $this->withHeader('User-Agent', 'Mozilla/5.0')->get('/en/poshuk/pidkazky?q=коледж')->assertOk();
        $this->assertFalse(SiteVisit::where('path', '/en/poshuk/pidkazky')->exists());
    }
}
