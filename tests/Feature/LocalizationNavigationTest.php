<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DocumentCategory;
use App\Models\Gallery;
use App\Models\News;
use App\Models\Page;
use App\Models\Specialty;
use App\Support\LocalizedHtml;
use App\Support\LocalizedUrl;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalizationNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_switcher_preserves_material_query_and_active_language(): void
    {
        $response = $this->get('/en/novyny?year=2026&category=test&page=2')->assertOk();
        $dom = new DOMDocument;
        @$dom->loadHTML($response->getContent());
        $links = (new DOMXPath($dom))->query('//nav[@aria-label="Language"]/a');
        $this->assertCount(2, $links);
        $this->assertSame(url('/novyny?category=test&page=2&year=2026'), $links[0]->getAttribute('href'));
        $this->assertSame('true', $links[1]->getAttribute('aria-current'));

        $page = Page::where('slug', 'abituriyentu')->firstOrFail();
        $this->get('/'.$page->slug)->assertOk()
            ->assertSee('href="'.url('/en/'.$page->slug).'" lang="en"', false);
    }

    public function test_public_navigation_and_forms_do_not_leave_english_locale(): void
    {
        $paths = ['/en', '/en/novyny', '/en/kontakty', '/en/kviz', '/en/faq',
            '/en/abituriyentu', '/en/studentu', '/en/spetsialnosti', '/en/struktura',
            '/en/dokumenty', '/en/halereya', '/en/podiyi', '/en/video', '/en/poshuk?q=коледж'];
        $paths[] = '/en/novyny/'.News::published()->firstOrFail()->slug;
        foreach ([
            Specialty::class => 'specialties.show',
            Department::class => 'structure.show',
            Gallery::class => 'galleries.show',
            DocumentCategory::class => 'documents.category',
        ] as $model => $route) {
            $paths[] = route('en.'.$route, $model::firstOrFail());
        }

        foreach ($paths as $path) {
            $html = $this->get($path)->assertOk()->getContent();
            $dom = new DOMDocument;
            @$dom->loadHTML($html);
            foreach ((new DOMXPath($dom))->query('//a[@href and not(@lang="uk")]|//form[@action]') as $element) {
                $url = $element->getAttribute($element->tagName === 'form' ? 'action' : 'href');
                if (! str_starts_with($url, url('/').'/') && ! str_starts_with($url, '/')) {
                    continue;
                }
                $target = parse_url($url, PHP_URL_PATH);
                if (preg_match('~^/(?:admin|storage|build|vendor)(?:/|$)|^/(?:sitemap\.xml|robots\.txt)$~', $target)) {
                    continue;
                }
                $this->assertTrue($target === '/en' || str_starts_with($target, '/en/'), $path.' links to '.$url);
            }
        }
    }

    public function test_content_links_are_localized_without_changing_saved_html_or_assets(): void
    {
        $body = '<p class="keep">Текст <a href="/faq?x=1&amp;y=2#answer">FAQ</a>'
            .'<a href=../novyny>News</a><a href="/storage/file.pdf">File</a>'
            .'<a href="https://example.org/faq">External</a><a href="#section">Section</a>'
            .'<img src="/storage/image.jpg"></p>';
        $page = Page::create(['title' => 'Посилання', 'slug' => 'posylannya', 'body' => $body, 'is_published' => true]);
        $this->get('/en/posylannya')->assertOk()
            ->assertSee('href="'.url('/en/faq?x=1&amp;y=2#answer').'"', false)
            ->assertSee('href="'.url('/en/novyny').'"', false)
            ->assertSee('href="/storage/file.pdf"', false)
            ->assertSee('href="https://example.org/faq"', false)
            ->assertSee('href="#section"', false)
            ->assertSee('src="/storage/image.jpg"', false);
        $this->assertSame($body, $page->fresh()->body);

        $untouched = '<!-- <a href="/faq"> -->'
            .'<script>const html = \'<a href="/faq">\';</script>'
            .'<style>/* <a href="/faq"> */</style>'
            .'<a data-href="/faq" title="href=\'/faq\'" href="mailto:test@example.org">Mail</a>';
        $this->assertSame($untouched, LocalizedHtml::links($untouched));
        $this->assertSame('/manual.pdf', LocalizedUrl::to('/manual.pdf'));
        $this->assertSame('manual.pdf', LocalizedUrl::to('manual.pdf'));
        $this->assertSame(url('/en/novyny?year=2026#list'), LocalizedUrl::to('../novyny?year=2026#list'));
    }

    public function test_retired_forms_do_not_accept_english_submissions(): void
    {
        $this->post('/en/zayavka', ['name' => 'Test', 'phone' => '123'])->assertStatus(405);
        $this->post('/en/kontakty', ['name' => 'Test', 'message' => 'Hello'])->assertStatus(405);
        $this->assertDatabaseCount('applicant_requests', 0);
        $this->assertDatabaseCount('feedback_messages', 0);
    }

    public function test_pagination_and_feed_links_keep_their_locale(): void
    {
        $year = now()->year;
        for ($i = 0; $i < 12; $i++) {
            News::create([
                'title' => 'Пагінація '.$i, 'slug' => 'pagination-'.$i,
                'body' => '<p>Текст</p>', 'published_at' => now()->subHour(), 'is_published' => true,
            ]);
        }
        $this->get('/en/novyny?year='.$year)->assertOk()
            ->assertSee(url('/en/novyny?year='.$year.'&amp;page=2'), false);
        $news = News::published()->firstOrFail();
        $this->get('/en/novyny/feed.xml')->assertOk()
            ->assertSee(url('/en/novyny/'.$news->slug), false)
            ->assertSee('href="'.url('/en/novyny/feed.xml').'"', false);
        $this->get('/en/nevidoma-storinka')->assertNotFound()
            ->assertSee('href="'.url('/en').'"', false);
    }
}
