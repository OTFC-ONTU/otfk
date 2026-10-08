<?php

namespace Tests\Feature;

use App\Filament\Pages\SeoSettings;
use App\Models\Banner;
use App\Models\News;
use App\Models\Setting;
use App\Models\User;
use App\Support\LazyMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Етап 3 docs/seo-plan.md: структурована розмітка (EducationalOrganization з
 * PostalAddress, alternateName/sameAs з адмінки, NewsArticle з абсолютними URL,
 * видавцем і київським зсувом дат) та базові атрибути LCP/CLS зображень.
 */
class StructuredDataTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<array<string, mixed>> усі JSON-LD блоки сторінки */
    private function jsonLd(string $html): array
    {
        preg_match_all('~<script type="application/ld\+json">(.*?)</script>~s', $html, $m);

        return array_map(fn ($json) => json_decode($json, true, flags: JSON_THROW_ON_ERROR), $m[1]);
    }

    private function ldOfType(string $html, string $type): array
    {
        foreach ($this->jsonLd($html) as $block) {
            if (($block['@type'] ?? null) === $type) {
                return $block;
            }
        }
        $this->fail("JSON-LD {$type} не знайдено");
    }

    private function setSetting(string $key, string $value, string $type = 'textarea'): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value, 'group' => 'seo', 'type' => $type]);
    }

    // ── EducationalOrganization ──────────────────────────────────────────

    public function test_organization_has_postal_address_and_absolute_logo_without_optional_fields(): void
    {
        $this->setSetting('contact_address', 'м. Одеса, вул. Прикладна, 1, 65000', 'text');
        $this->setSetting('seo_alternate_names', '');
        $this->setSetting('seo_same_as', '');

        $org = $this->ldOfType($this->get('/')->assertOk()->getContent(), 'EducationalOrganization');

        $this->assertSame('PostalAddress', $org['address']['@type']);
        $this->assertSame('м. Одеса, вул. Прикладна, 1, 65000', $org['address']['streetAddress']);
        $this->assertSame('Одеса', $org['address']['addressLocality']);
        $this->assertSame('65000', $org['address']['postalCode']);
        $this->assertSame('UA', $org['address']['addressCountry']);
        $this->assertMatchesRegularExpression('~^https?://~', $org['logo']);
        $this->assertArrayNotHasKey('sameAs', $org);
        $this->assertArrayNotHasKey('alternateName', $org);

        // Англійська версія — місто англійською
        $en = $this->ldOfType($this->get('/en')->assertOk()->getContent(), 'EducationalOrganization');
        $this->assertSame('Odesa', $en['address']['addressLocality']);
    }

    public function test_organization_outputs_alternate_names_and_same_as_when_set(): void
    {
        $this->setSetting('seo_alternate_names', "ОТФК ОНТУ\n\n  Одеський технічний коледж  \nОТФК ОНТУ");
        $this->setSetting('seo_same_as', "https://www.facebook.com/otfk.official\nhttp://insecure.example.com\njavascript:alert(1)");

        $org = $this->ldOfType($this->get('/')->assertOk()->getContent(), 'EducationalOrganization');

        $this->assertSame(['ОТФК ОНТУ', 'Одеський технічний коледж'], $org['alternateName']);
        // У розмітку потрапляють лише https-адреси, навіть якщо в БД щось інше (SQL-правка)
        $this->assertSame(['https://www.facebook.com/otfk.official'], $org['sameAs']);
    }

    public function test_json_ld_escapes_tags(): void
    {
        $this->setSetting('seo_alternate_names', '</script><script>alert(1)</script>');

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('</script><script>alert(1)', $html);
        $org = $this->ldOfType($html, 'EducationalOrganization');
        $this->assertSame(['</script><script>alert(1)</script>'], $org['alternateName']);
    }

    // ── Сторінка налаштувань ─────────────────────────────────────────────

    public function test_seo_settings_page_is_admin_only(): void
    {
        $this->actingAs(User::factory()->editor()->create());

        $this->get(SeoSettings::getUrl())->assertForbidden();
        Livewire::test(SeoSettings::class)->assertForbidden();

        $this->actingAs(User::factory()->create());
        $this->get(SeoSettings::getUrl())->assertOk()->assertSee('Офіційні профілі');
    }

    public function test_seo_settings_validate_https_urls_and_save_clean_lines(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(SeoSettings::class)
            ->set('data.seo_same_as', "https://www.instagram.com/otfk\njavascript:alert(1)")
            ->call('save')
            ->assertHasErrors(['data.seo_same_as']);

        Livewire::test(SeoSettings::class)
            ->set('data.seo_same_as', 'http://example.com')
            ->call('save')
            ->assertHasErrors(['data.seo_same_as']);

        $this->assertNull(Setting::get('seo_same_as'));

        Livewire::test(SeoSettings::class)
            ->set('data.seo_alternate_names', "  ОТФК ОНТУ \n\n")
            ->set('data.seo_same_as', " https://www.instagram.com/otfk \n\nhttps://edbo.gov.ua/vnz/0000/ ")
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('ОТФК ОНТУ', Setting::get('seo_alternate_names'));
        $this->assertSame("https://www.instagram.com/otfk\nhttps://edbo.gov.ua/vnz/0000/", Setting::get('seo_same_as'));

        $org = $this->ldOfType($this->get('/')->getContent(), 'EducationalOrganization');
        $this->assertSame(['https://www.instagram.com/otfk', 'https://edbo.gov.ua/vnz/0000/'], $org['sameAs']);
    }

    // ── NewsArticle ──────────────────────────────────────────────────────

    public function test_news_article_has_absolute_image_publisher_and_kyiv_offsets(): void
    {
        Bus::fake();
        Storage::fake('public');
        Storage::disk('public')->putFileAs('news', UploadedFile::fake()->image('cover.jpg', 800, 450), 'cover.jpg');

        Carbon::setTestNow('2026-07-15 09:00:00'); // UTC: справжня позначка updated_at
        $news = News::create([
            'title' => str_repeat('Дуже довгий заголовок новини ', 8),
            'body' => '<p>Текст</p><p><img src="/storage/news/a.jpg" alt=""></p><iframe src="https://www.youtube.com/embed/x"></iframe>',
            'cover_image' => 'news/cover.jpg',
            'is_published' => true,
            // Київський wall-clock, як його вводить редактор
            'published_at' => '2026-07-14 10:30:00',
        ]);
        Carbon::setTestNow();

        $html = $this->get('/novyny/'.$news->slug)->assertOk()->getContent();
        $article = $this->ldOfType($html, 'NewsArticle');

        $this->assertLessThanOrEqual(110, mb_strlen($article['headline']));
        $this->assertSame('2026-07-14T10:30:00+03:00', $article['datePublished']);
        // updated_at = 09:00 UTC → 12:00 за Києвом (літо, +03:00), а не 09:00+03:00
        $this->assertSame('2026-07-15T12:00:00+03:00', $article['dateModified']);
        $this->assertMatchesRegularExpression('~^https?://[^/]+/storage/news/cover\.jpg$~', $article['image'][0]);
        $this->assertSame('WebPage', $article['mainEntityOfPage']['@type']);
        $this->assertStringEndsWith('/novyny/'.$news->slug, $article['mainEntityOfPage']['@id']);
        $this->assertMatchesRegularExpression('~^https?://~', $article['mainEntityOfPage']['@id']);
        $this->assertSame('Organization', $article['publisher']['@type']);
        $this->assertNotEmpty($article['publisher']['name']);
        $this->assertMatchesRegularExpression('~^https?://~', $article['publisher']['logo']['url']);
        $this->assertSame('Organization', $article['author']['@type']);

        // Обкладинка — головне зображення: без lazy, з пріоритетом і природними розмірами
        $this->assertMatchesRegularExpression('~<img src="[^"]*news/cover\.jpg"[^>]*width="800" height="450"[^>]*fetchpriority="high"~', $html);
        $this->assertDoesNotMatchRegularExpression('~<img src="[^"]*news/cover\.jpg"[^>]*loading="lazy"~', $html);
        // Зображення й iframe тексту — ліниві (є обкладинка, тож і перше)
        $this->assertMatchesRegularExpression('~<img src="/storage/news/a.jpg"[^>]*loading="lazy" decoding="async"~', $html);
        $this->assertMatchesRegularExpression('~<iframe[^>]*youtube[^>]*loading="lazy"~', $html);

        // Відносний шлях у хлібних крихтах стає абсолютним
        $crumbs = $this->ldOfType($html, 'BreadcrumbList');
        foreach (array_filter(array_column($crumbs['itemListElement'], 'item')) as $item) {
            $this->assertMatchesRegularExpression('~^https?://~', $item);
        }
    }

    public function test_date_modified_is_never_before_date_published(): void
    {
        Bus::fake();
        Carbon::setTestNow('2026-07-01 09:00:00');
        $news = News::create([
            'title' => 'Новина з датою в минулому, змінена раніше',
            'body' => '<p>Текст</p>',
            'is_published' => true,
            'published_at' => '2026-07-01 11:30:00', // 11:30 Київ = 08:30 UTC, раніше за updated_at
        ]);
        Carbon::setTestNow('2026-07-02 00:00:00');

        $article = $this->ldOfType($this->get('/novyny/'.$news->slug)->assertOk()->getContent(), 'NewsArticle');
        $this->assertSame('2026-07-01T11:30:00+03:00', $article['datePublished']);
        $this->assertSame('2026-07-01T12:00:00+03:00', $article['dateModified']);
        Carbon::setTestNow();
    }

    // ── LCP/CLS ──────────────────────────────────────────────────────────

    public function test_home_hero_has_high_priority_and_cards_are_lazy(): void
    {
        Bus::fake();
        Storage::fake('public');
        Storage::disk('public')->putFileAs('banners', UploadedFile::fake()->image('hero.jpg', 1600, 700), 'hero.jpg');
        Storage::disk('public')->putFileAs('news', UploadedFile::fake()->image('card.jpg', 640, 360), 'card.jpg');

        Banner::query()->delete();
        Banner::create(['title' => 'Головний банер', 'image' => 'banners/hero.jpg', 'is_published' => true]);
        News::create(['title' => 'Картка новини', 'body' => '<p>x</p>', 'cover_image' => 'news/card.jpg',
            'is_published' => true, 'published_at' => now()->subDay()]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('~<img src="[^"]*banners/hero\.jpg"[^>]*width="1600" height="700"[^>]*fetchpriority="high"~', $html);
        $this->assertDoesNotMatchRegularExpression('~<img src="[^"]*banners/hero\.jpg"[^>]*loading="lazy"~', $html);
        $this->assertMatchesRegularExpression('~<img src="[^"]*news/card\.jpg"[^>]*loading="lazy" decoding="async"~', $html);
    }

    public function test_lazy_media_keeps_first_image_eager_on_request_and_respects_existing_attributes(): void
    {
        $html = '<!-- <img src="c.jpg"> --><p><img src="a.jpg" alt=""></p><img src="b.jpg" loading="eager" />'
            .'<iframe src="https://maps.google.com/x" loading="eager"></iframe><iframe src="https://www.youtube.com/embed/y"></iframe>';

        $out = LazyMedia::render($html, eagerFirst: true);

        $this->assertStringContainsString('<!-- <img src="c.jpg"> -->', $out);
        $this->assertStringContainsString('<img src="a.jpg" alt="" decoding="async">', $out);
        $this->assertStringContainsString('<img src="b.jpg" loading="eager" decoding="async" />', $out);
        $this->assertStringContainsString('<iframe src="https://maps.google.com/x" loading="eager">', $out);
        $this->assertStringContainsString('<iframe src="https://www.youtube.com/embed/y" loading="lazy">', $out);
    }
}
