<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Шапка: оголошення з окремою кнопкою переходу, десктоп-меню в один рядок із «Ще»,
 * мобільний ряд без перемикача мови (він у висувному меню).
 */
class HeaderLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_announcement_has_separate_read_more_link(): void
    {
        Setting::where('key', 'announcement_text')->update(['value' => 'Розпочато вступну кампанію']);
        Setting::where('key', 'announcement_url')->update(['value' => '/abituriyentu']);
        cache()->forget('settings.map');

        $this->get('/')
            ->assertOk()
            ->assertSee('id="announcement"', escape: false)
            ->assertSee('Розпочато вступну кампанію')
            ->assertSee('Детальніше');
    }

    public function test_desktop_navigation_is_single_row_with_overflow_menu(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('navOverflow(', $html);
        $this->assertStringContainsString('data-nav-more', $html);
        $this->assertStringContainsString(__('layout.nav_more'), $html);
    }

    public function test_mobile_header_row_hides_language_switcher_and_drawer_has_one(): void
    {
        $html = $this->get('/en')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<div class="hidden sm:block">\s*<nav aria-label="Language"/', $html);
        $this->assertStringContainsString('aria-label="Language (menu)"', $html);
        $this->assertStringContainsString('aria-label="Close menu"', $html);
    }
}
