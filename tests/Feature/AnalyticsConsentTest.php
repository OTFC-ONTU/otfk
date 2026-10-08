<?php

namespace Tests\Feature;

use App\Filament\Pages\SeoSettings;
use App\Models\Page;
use App\Models\Setting;
use App\Models\SiteVisit;
use App\Models\User;
use App\Support\Analytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * GA4 з консервативною згодою (docs/seo-plan.md, «Аналітика»): без
 * Measurement ID, на тестовому домені й для персоналу, що увійшов, — ні
 * банера, ні конфігурації; сервер ніколи не віддає скрипт googletagmanager.
 * Статистика відвідувань (TrackVisits) так само не враховує персонал і
 * неосновний домен.
 */
class AnalyticsConsentTest extends TestCase
{
    use RefreshDatabase;

    private const ID = 'G-TEST12345';

    private function setMeasurementId(string $value): void
    {
        Setting::updateOrCreate(['key' => Analytics::MEASUREMENT_ID_KEY], ['value' => $value, 'group' => 'seo', 'type' => 'text']);
    }

    private function assertNoAnalytics(string $html): void
    {
        $this->assertStringNotContainsString('id="analytics-consent"', $html);
        $this->assertStringNotContainsString('data-ga4-id', $html);
        $this->assertStringNotContainsString('data-analytics-settings', $html);
        $this->assertStringNotContainsString('googletagmanager.com', $html);
    }

    public function test_nothing_is_rendered_without_measurement_id(): void
    {
        $this->assertNoAnalytics($this->get('/')->assertOk()->getContent());

        // Неправильний формат у БД (напр., внесений через «Розширені налаштування») — теж вимкнено.
        $this->setMeasurementId('UA-12345-1');
        $this->assertNoAnalytics($this->get('/')->assertOk()->getContent());
    }

    public function test_banner_and_config_for_guest_on_primary_domain_in_both_locales(): void
    {
        $this->setMeasurementId(self::ID);

        // Без опублікованої сторінки політики посилання не виводиться (без 404).
        $this->assertStringNotContainsString('polityka-konfidentsiynosti', $this->get('/')->getContent());
        Page::create(['title' => 'Політика конфіденційності', 'slug' => 'polityka-konfidentsiynosti', 'is_published' => true]);

        $uk = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('data-ga4-id="'.self::ID.'"', $uk);
        $this->assertStringContainsString('data-consent-banner hidden', $uk);
        $this->assertStringContainsString('data-query-keys="[&quot;page&quot;,&quot;category&quot;,&quot;year&quot;]"', $uk);
        $this->assertStringContainsString('Прийняти', $uk);
        $this->assertStringContainsString('Відхилити', $uk);
        $this->assertStringContainsString('Налаштування cookies', $uk);
        $this->assertStringContainsString('href="'.url('/polityka-konfidentsiynosti').'"', $uk);
        $this->assertStringNotContainsString('googletagmanager.com/gtag/js', $uk);
        $this->assertDoesNotMatchRegularExpression('~<script[^>]+src="[^"]*googletagmanager~i', $uk);

        $en = $this->get('/en')->assertOk()->getContent();
        $this->assertStringContainsString('data-ga4-id="'.self::ID.'"', $en);
        $this->assertStringContainsString('Accept', $en);
        $this->assertStringContainsString('Decline', $en);
        $this->assertStringContainsString('Cookie settings', $en);
        $this->assertStringContainsString('href="'.url('/en/polityka-konfidentsiynosti').'"', $en);
        $this->assertStringNotContainsString('googletagmanager.com/gtag/js', $en);
    }

    public function test_non_primary_host_shows_banner_but_never_gets_loadable_id(): void
    {
        $this->setMeasurementId(self::ID);
        config(['otfk.seo.indexing' => 'auto', 'otfk.seo.primary_host' => 'otfk.od.ua']);

        // Тестовий хостинг: банер і посилання в підвалі як на проді, але без ID
        // і з позначкою data-analytics-disabled — analytics.js не завантажить gtag.
        $test = $this->get('http://just-test.shop/')->assertOk()->getContent();
        $this->assertStringContainsString('id="analytics-consent"', $test);
        $this->assertStringContainsString('data-analytics-disabled', $test);
        $this->assertStringContainsString('data-consent-accept', $test);
        $this->assertStringContainsString('data-analytics-settings', $test);
        $this->assertStringNotContainsString('data-ga4-id', $test);
        $this->assertStringNotContainsString(self::ID, $test);
        $this->assertStringNotContainsString('googletagmanager.com', $test);

        // Основний домен: той самий банер плюс ID для завантаження після згоди.
        $primary = $this->get('http://otfk.od.ua/')->assertOk()->getContent();
        $this->assertStringContainsString('data-ga4-id="'.self::ID.'"', $primary);
        $this->assertStringNotContainsString('data-analytics-disabled', $primary);
        $this->assertStringNotContainsString('googletagmanager.com', $primary);
    }

    public function test_no_banner_on_any_host_without_measurement_id(): void
    {
        config(['otfk.seo.indexing' => 'auto', 'otfk.seo.primary_host' => 'otfk.od.ua']);

        $this->assertNoAnalytics($this->get('http://just-test.shop/')->assertOk()->getContent());
        $this->assertNoAnalytics($this->get('http://otfk.od.ua/')->assertOk()->getContent());
    }

    public function test_nothing_is_rendered_for_logged_in_staff(): void
    {
        $this->setMeasurementId(self::ID);

        $this->actingAs(User::factory()->editor()->create());
        $this->assertNoAnalytics($this->get('/')->assertOk()->getContent());
    }

    public function test_settings_page_validates_measurement_id_and_is_admin_only(): void
    {
        $this->actingAs(User::factory()->editor()->create());
        $this->get(SeoSettings::getUrl())->assertForbidden();
        Livewire::test(SeoSettings::class)->assertForbidden();

        $this->actingAs(User::factory()->create());
        $this->get(SeoSettings::getUrl())->assertOk()->assertSee('Measurement ID');

        foreach (['UA-12345-1', 'G-12', 'G-ABC DEF', '<script>', 'G-'.str_repeat('A', 21)] as $bad) {
            Livewire::test(SeoSettings::class)
                ->set('data.'.Analytics::MEASUREMENT_ID_KEY, $bad)
                ->call('save')
                ->assertHasErrors(['data.'.Analytics::MEASUREMENT_ID_KEY]);
        }
        $this->assertNull(Setting::get(Analytics::MEASUREMENT_ID_KEY));

        Livewire::test(SeoSettings::class)
            ->set('data.'.Analytics::MEASUREMENT_ID_KEY, ' g-abc123xyz ')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertSame('G-ABC123XYZ', Setting::get(Analytics::MEASUREMENT_ID_KEY));

        // Порожнє значення дозволене й вимикає аналітику.
        Livewire::test(SeoSettings::class)
            ->set('data.'.Analytics::MEASUREMENT_ID_KEY, '')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertSame('', Setting::get(Analytics::MEASUREMENT_ID_KEY));
        $this->assertNull(Analytics::measurementId());
    }

    public function test_visits_are_not_counted_for_staff_or_non_primary_host(): void
    {
        $this->actingAs(User::factory()->editor()->create());
        $this->withHeader('User-Agent', 'Mozilla/5.0')->get('/novyny')->assertOk();
        $this->assertFalse(SiteVisit::where('path', '/novyny')->exists());

        auth()->logout();
        config(['otfk.seo.indexing' => 'auto', 'otfk.seo.primary_host' => 'otfk.od.ua']);
        $this->withHeader('User-Agent', 'Mozilla/5.0')->get('http://just-test.shop/novyny')->assertOk();
        $this->assertFalse(SiteVisit::where('path', '/novyny')->exists());

        $this->withHeader('User-Agent', 'Mozilla/5.0')->get('http://otfk.od.ua/novyny')->assertOk();
        $this->assertTrue(SiteVisit::where('path', '/novyny')->exists());
    }
}
