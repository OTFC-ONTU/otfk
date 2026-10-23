<?php

namespace Tests\Feature;

use App\Filament\Pages\AppearanceSettings;
use App\Models\QuickLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Партнери в підвалі: стороннім сайтам — rel="nofollow" (не «ручаємося» за них),
 * своїм (ОНТУ, МОН) і внутрішнім сторінкам — без нього; прапорець у «Підвал і вигляд».
 */
class FooterPartnerNofollowTest extends TestCase
{
    use RefreshDatabase;

    private function partner(string $title, string $url, array $extra = []): QuickLink
    {
        return QuickLink::create($extra + ['location' => 'footer_partner', 'title' => $title, 'url' => $url, 'is_visible' => true, 'open_new_tab' => true]);
    }

    public function test_new_partner_is_nofollow_by_default(): void
    {
        $this->assertTrue($this->partner('Сторонній', 'https://example.test')->fresh()->nofollow);
    }

    public function test_footer_marks_only_flagged_external_partners_nofollow(): void
    {
        QuickLink::location('footer_partner')->delete();
        $this->partner('Сторонній партнер', 'https://example.test/partner');
        $this->partner('Університет', 'https://ontu.edu.ua', ['nofollow' => false]);
        $this->partner('Внутрішня сторінка', '/povidom-pro-koruptsiyu', ['open_new_tab' => false]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('~<a href="https://example\.test/partner"\s+target="_blank"\s+rel="nofollow noopener"~', $html);
        $this->assertMatchesRegularExpression('~<a href="https://ontu\.edu\.ua"\s+target="_blank"\s+rel="noopener"~', $html);
        $this->assertMatchesRegularExpression('~<a href="[^"]*/povidom-pro-koruptsiyu"\s+class=~', $html);
    }

    public function test_own_domain_is_never_nofollow(): void
    {
        config(['otfk.seo.primary_host' => 'otfk.od.ua']);

        $this->assertNull($this->partner('Свій', 'https://www.otfk.od.ua/x', ['open_new_tab' => false])->rel());
        $this->assertSame('nofollow', $this->partner('Чужий', '//example.test', ['open_new_tab' => false])->rel());
    }

    public function test_appearance_settings_saves_nofollow_flag(): void
    {
        $this->actingAs(User::firstOrFail());
        QuickLink::location('footer_partner')->delete();
        $link = $this->partner('Міністерство', 'https://mon.gov.ua');

        $component = Livewire::test(AppearanceSettings::class);
        $key = array_key_first($component->get('data.partners'));
        $this->assertTrue($component->get("data.partners.$key.nofollow"));

        $component->set("data.partners.$key.nofollow", false)->call('save')->assertHasNoErrors();

        $this->assertFalse($link->fresh()->nofollow);
    }
}
