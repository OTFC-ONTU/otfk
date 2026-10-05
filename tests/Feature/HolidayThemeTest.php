<?php

namespace Tests\Feature;

use App\Filament\Pages\HolidaySettings;
use App\Models\Setting;
use App\Models\User;
use App\Support\HolidayTheme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Святкові теми (ключі settings `holiday_theme`/`holiday_theme_until`,
 * довідник App\Support\HolidayTheme): без теми прикрас немає; активна тема
 * перефарбовує шапку/підвал, вішає SVG-гірлянду, значок і вітання; невідоме
 * значення й минула дата автовимкнення ігноруються; тема керується зі
 * сторінки «Святкова тема».
 */
class HolidayThemeTest extends TestCase
{
    use RefreshDatabase;

    private function setTheme(string $key, string $until = ''): void
    {
        Setting::updateOrCreate(['key' => 'holiday_theme'], ['value' => $key, 'group' => 'appearance']);
        Setting::updateOrCreate(['key' => 'holiday_theme_until'], ['value' => $until, 'group' => 'appearance']);
    }

    public function test_no_decorations_by_default(): void
    {
        $this->get('/')->assertOk()
            ->assertDontSee('data-holiday', false)
            ->assertDontSee('hd-garland');
    }

    public function test_active_theme_renders_decorations_and_greeting(): void
    {
        $this->setTheme('new_year');

        $this->get('/')->assertOk()
            ->assertSee('data-holiday="new_year"', false)
            ->assertSee('data-holiday-particles="snow"', false)
            ->assertSee('--hd-nav:#12442f', false)
            ->assertSee('hd-garland-lights', false)
            ->assertSee('holiday-logo-badge', false)
            ->assertSee('З Новим роком і Різдвом Христовим!');

        $this->get('/en')->assertOk()->assertSee('Happy New Year and Merry Christmas!');
    }

    public function test_unknown_or_expired_theme_is_ignored(): void
    {
        $this->setTheme('no-such-theme');
        $this->get('/')->assertOk()->assertDontSee('data-holiday', false);

        $this->setTheme('halloween', Carbon::now('Europe/Kyiv')->subDay()->format('Y-m-d'));
        $this->assertNull(HolidayTheme::active());
        $this->get('/')->assertOk()->assertDontSee('data-holiday', false);

        // Дата автовимкнення — включно до кінця дня за Києвом
        $this->setTheme('halloween', Carbon::now('Europe/Kyiv')->format('Y-m-d'));
        $this->assertSame('halloween', HolidayTheme::active());
    }

    public function test_every_theme_is_complete_and_renders(): void
    {
        $badges = ['snowflake', 'egg', 'ornament', 'heart', 'bell', 'code', 'leaf', 'wheat', 'pumpkin', 'bolt'];

        foreach (HolidayTheme::all() as $key => $theme) {
            $this->assertNotSame('', $theme['label'], "Тема {$key} без назви");
            $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $theme['accent'], "Тема {$key}: акцент");
            $this->assertContains($theme['garland']['type'], ['lights', 'bunting', 'embroidery'], "Тема {$key}: гірлянда");
            $this->assertContains($theme['particles']['type'], ['snow', 'leaves', 'petals', 'confetti', 'code', 'sparks'], "Тема {$key}: частинки");
            $this->assertContains($theme['badge'], $badges, "Тема {$key}: значок");
            $this->assertNotSame('layout.holiday.'.$key, __('layout.holiday.'.$key), "Тема {$key} без вітання uk");
            $this->assertArrayHasKey($key, trans('layout.holiday', [], 'en'), "Тема {$key} без вітання en");

            $this->setTheme($key);
            $this->get('/')->assertOk()->assertSee('data-holiday="'.$key.'"', false);
        }
    }

    public function test_admin_page_saves_and_clears_theme(): void
    {
        $this->actingAs(User::firstOrFail());
        $this->get(HolidaySettings::getUrl())->assertOk()->assertSee('Святкова тема');

        Livewire::test(HolidaySettings::class)
            ->set('data.holiday_theme', 'independence')
            ->assertSee('З Днем Незалежності України!')
            ->set('data.holiday_theme_until', '2099-08-25')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('independence', Setting::get('holiday_theme'));
        $this->assertSame('2099-08-25', Setting::get('holiday_theme_until'));
        $this->assertSame('independence', HolidayTheme::active());

        // Звичайний вигляд скидає й дату автовимкнення
        Livewire::test(HolidaySettings::class)
            ->set('data.holiday_theme', '')
            ->call('save');

        $this->assertSame('', Setting::get('holiday_theme'));
        $this->assertSame('', Setting::get('holiday_theme_until'));
        $this->get('/')->assertDontSee('data-holiday', false);
    }
}
