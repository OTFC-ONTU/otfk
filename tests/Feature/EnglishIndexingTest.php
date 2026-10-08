<?php

namespace Tests\Feature;

use App\Http\Controllers\SitemapController;
use App\Models\Department;
use App\Models\Gallery;
use App\Models\News;
use App\Models\Page;
use App\Models\Photo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Етап 4 docs/seo-plan.md: `/en` індексується автоматично — розділи з
 * перекладеним каркасом і матеріали з повним опублікованим незастарілим
 * перекладом; hreflang лише для пар, де обидві версії індексуються.
 */
class EnglishIndexingTest extends TestCase
{
    use RefreshDatabase;

    private function translatedPage(string $slug, array $attributes = []): Page
    {
        return Page::create($attributes + [
            'title' => 'Сторінка '.$slug, 'slug' => $slug, 'body' => '<p>Текст</p>', 'is_published' => true,
            'title_en' => 'Page '.$slug, 'body_en' => '<p>Text</p>', 'translation_published' => true,
        ]);
    }

    private function translatedNews(string $slug, array $attributes = []): News
    {
        return News::create($attributes + [
            'title' => 'Новина '.$slug, 'slug' => $slug, 'body' => '<p>Текст</p>',
            'is_published' => true, 'published_at' => now()->subDay(),
            'title_en' => 'News '.$slug, 'body_en' => '<p>Text</p>', 'translation_published' => true,
        ]);
    }

    private function assertIndexable(TestResponse $response): TestResponse
    {
        $response->assertOk()->assertHeaderMissing('X-Robots-Tag');
        $this->assertStringNotContainsString('noindex', $response->getContent());

        return $response;
    }

    private function assertHreflangPair(TestResponse $response, string $uk, string $en): void
    {
        $response
            ->assertSee('<link rel="alternate" hreflang="uk" href="'.$uk.'">', false)
            ->assertSee('<link rel="alternate" hreflang="en" href="'.$en.'">', false)
            ->assertSee('<link rel="alternate" hreflang="x-default" href="'.$uk.'">', false);
    }

    private function assertNoindexWithoutAlternates(TestResponse $response): void
    {
        $response->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, follow')
            ->assertSee('<meta name="robots" content="noindex, follow">', false)
            ->assertDontSee('rel="canonical"', false)
            ->assertDontSee('hreflang=', false);
    }

    public function test_translated_page_is_self_canonical_with_reciprocal_hreflang(): void
    {
        $this->translatedPage('pereklad-seo');

        $en = $this->assertIndexable($this->get('/en/pereklad-seo'));
        $en->assertSee('<link rel="canonical" href="'.url('/en/pereklad-seo').'">', false);
        $this->assertHreflangPair($en, url('/pereklad-seo'), url('/en/pereklad-seo'));

        $uk = $this->assertIndexable($this->get('/pereklad-seo'));
        $uk->assertSee('<link rel="canonical" href="'.url('/pereklad-seo').'">', false);
        $this->assertHreflangPair($uk, url('/pereklad-seo'), url('/en/pereklad-seo'));
    }

    public function test_editing_original_makes_english_noindex_until_translation_is_updated(): void
    {
        $page = $this->translatedPage('zastarilyi-seo');
        $page->update(['body' => '<p>Новий текст оригіналу</p>']);
        $this->assertTrue($page->fresh()->translationIsStale());

        $this->assertNoindexWithoutAlternates($this->get('/en/zastarilyi-seo'));
        // Українська версія індексується, але без hreflang на застарілий переклад.
        $this->assertIndexable($this->get('/zastarilyi-seo'))->assertDontSee('hreflang=', false);

        // Оновлений переклад знову відкриває англійську версію.
        $page->fresh()->update(['body_en' => '<p>New original text</p>']);
        $this->assertIndexable($this->get('/en/zastarilyi-seo'));
    }

    public function test_ukrainian_fallback_on_english_url_is_noindex(): void
    {
        Page::create(['title' => 'Без перекладу', 'slug' => 'bez-perekladu-seo', 'body' => '<p>Текст</p>', 'is_published' => true]);
        // Чернетка перекладу теж не відкриває /en.
        $this->translatedPage('chernetka-perekladu-seo', ['translation_published' => false]);

        $this->assertNoindexWithoutAlternates($this->get('/en/bez-perekladu-seo'));
        $this->assertNoindexWithoutAlternates($this->get('/en/chernetka-perekladu-seo'));
        $this->assertIndexable($this->get('/bez-perekladu-seo'))->assertDontSee('hreflang=', false);
    }

    public function test_english_listing_is_indexable_with_hreflang_preserving_query(): void
    {
        foreach (range(1, 12) as $i) {
            $this->translatedNews('spysok-seo-'.$i);
        }

        $en = $this->assertIndexable($this->get('/en/novyny?page=2&utm_source=x'));
        $en->assertSee('<link rel="canonical" href="'.url('/en/novyny').'?page=2">', false);
        $this->assertHreflangPair($en, url('/novyny').'?page=2', url('/en/novyny').'?page=2');

        $this->assertHreflangPair($this->get('/novyny?page=2'), url('/novyny').'?page=2', url('/en/novyny').'?page=2');
        $this->assertHreflangPair($this->assertIndexable($this->get('/en')), url('/'), url('/en'));
        $this->assertHreflangPair($this->assertIndexable($this->get('/en/novyny/spysok-seo-1')), url('/novyny/spysok-seo-1'), url('/en/novyny/spysok-seo-1'));
    }

    public function test_search_rss_and_errors_on_english_stay_noindex(): void
    {
        $this->get('/en/poshuk?q=college')->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, follow')
            ->assertDontSee('rel="canonical"', false)
            ->assertDontSee('hreflang=', false);
        $this->get('/en/novyny/feed.xml')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, follow');
        $this->get('/en/novyny/nemaye-seo')->assertNotFound()->assertHeader('X-Robots-Tag', 'noindex, follow');
        // Архів за роком лишається noindex і без hreflang на обох мовах.
        $this->get('/en/novyny?year=2020')->assertOk()->assertDontSee('hreflang=', false)
            ->assertSee('<meta name="robots" content="noindex, follow">', false);
    }

    public function test_non_primary_host_stays_closed_even_for_translated_pages(): void
    {
        config(['otfk.seo.indexing' => 'auto', 'otfk.seo.primary_host' => 'otfk.od.ua']);
        $this->translatedPage('khost-seo');

        foreach (['https://just-test.shop/en/khost-seo', 'https://just-test.shop/en', 'https://just-test.shop/khost-seo'] as $url) {
            $this->get($url)->assertOk()
                ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
                ->assertDontSee('rel="canonical"', false)
                ->assertDontSee('hreflang=', false);
        }

        $this->assertIndexable($this->get('https://otfk.od.ua/en/khost-seo'));
    }

    public function test_gallery_requires_translated_photo_captions(): void
    {
        $gallery = Gallery::create([
            'title' => 'Альбом SEO', 'slug' => 'albom-seo', 'description' => 'Опис', 'is_published' => true,
            'title_en' => 'Album SEO', 'description_en' => 'Description', 'translation_published' => true,
        ]);
        $photo = Photo::create(['gallery_id' => $gallery->id, 'image' => 'gallery/seo.jpg', 'caption' => 'Підпис']);

        $this->assertNoindexWithoutAlternates($this->get('/en/halereya/albom-seo'));
        $this->assertStringNotContainsString(url('/en/halereya/albom-seo'), $this->get('/sitemap.xml')->getContent());

        $photo->update(['caption_en' => 'Caption', 'translation_published' => true]);
        $this->assertIndexable($this->get('/en/halereya/albom-seo'));
        // Підпис фото скидає кеш карти сайту.
        $this->assertStringContainsString('<loc>'.url('/en/halereya/albom-seo').'</loc>', $this->get('/sitemap.xml')->getContent());
    }

    public function test_sitemap_lists_english_pairs_only_for_indexable_translations(): void
    {
        $this->translatedPage('para-seo');
        $this->translatedPage('zastarila-para-seo')->update(['title' => 'Змінений оригінал']);
        Page::create(['title' => 'Лише українська', 'slug' => 'lyshe-ukrayinska-seo', 'body' => '<p>Т</p>', 'is_published' => true]);
        $this->translatedPage('chernetka-para-seo', ['is_published' => false]);
        $this->translatedNews('novyna-para-seo');
        $this->translatedNews('maybutnya-para-seo', ['published_at' => now()->addDay()]);
        $this->translatedNews('chernetka-novyna-seo', ['is_published' => false]);
        Department::create([
            'title' => 'Відділення пари', 'slug' => 'viddilennya-para-seo', 'type' => 'viddilennya', 'is_published' => true,
            'title_en' => 'Pair department', 'translation_published' => true,
        ]);

        $body = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString('xmlns:xhtml="http://www.w3.org/1999/xhtml"', $body);
        $alternates = fn (string $uk, string $en) => '<xhtml:link rel="alternate" hreflang="uk" href="'.$uk.'"/>'
            .'<xhtml:link rel="alternate" hreflang="en" href="'.$en.'"/>'
            .'<xhtml:link rel="alternate" hreflang="x-default" href="'.$uk.'"/>';

        foreach ([['/para-seo', '/en/para-seo'], ['/novyny/novyna-para-seo', '/en/novyny/novyna-para-seo'],
            ['/struktura/viddilennya-para-seo', '/en/struktura/viddilennya-para-seo'], ['/', '/en'], ['/novyny', '/en/novyny']] as [$uk, $en]) {
            $this->assertStringContainsString('<loc>'.url($en).'</loc>', $body, $en);
            // Набір alternate однаковий на обох записах пари.
            $this->assertSame(2, substr_count($body, $alternates(url($uk), url($en))), $en);
        }

        $this->assertStringContainsString('<loc>'.url('/zastarila-para-seo').'</loc>', $body);
        $this->assertStringContainsString('<loc>'.url('/lyshe-ukrayinska-seo').'</loc>', $body);
        foreach (['/en/zastarila-para-seo', '/en/lyshe-ukrayinska-seo', 'chernetka-para-seo', 'maybutnya-para-seo', 'chernetka-novyna-seo', '/en/poshuk', 'feed.xml'] as $missing) {
            $this->assertStringNotContainsString($missing, $body, $missing);
        }
        // Українська адреса без перекладу — без xhtml:link.
        $this->assertMatchesRegularExpression('~<url><loc>'.preg_quote(url('/lyshe-ukrayinska-seo'), '~').'</loc>(?:(?!</url>).)*</url>~', $body);
        $this->assertDoesNotMatchRegularExpression('~<loc>'.preg_quote(url('/lyshe-ukrayinska-seo'), '~').'</loc>(?:(?!</url>).)*xhtml:link~', $body);
    }

    public function test_translation_update_flushes_sitemap_cache(): void
    {
        $page = Page::create(['title' => 'Кеш', 'slug' => 'kesh-seo', 'body' => '<p>Т</p>', 'is_published' => true]);
        $this->assertStringNotContainsString(url('/en/kesh-seo'), $this->get('/sitemap.xml')->getContent());
        $this->assertTrue(Cache::has(SitemapController::CACHE_KEY));

        $page->update(['title_en' => 'Cache', 'body_en' => '<p>T</p>', 'translation_published' => true]);

        $this->assertStringContainsString('<loc>'.url('/en/kesh-seo').'</loc>', $this->get('/sitemap.xml')->getContent());
    }
}
