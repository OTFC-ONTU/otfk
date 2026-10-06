<?php

namespace Tests\Feature;

use App\Filament\Pages\BellSchedule;
use App\Models\BellPeriod;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BellScheduleLayoutTest extends TestCase
{
    use RefreshDatabase;

    private function forgetCaches(): void
    {
        cache()->forget('settings.map');
        cache()->forget('bell_periods.v3');
    }

    public function test_page_shows_both_shifts_with_real_times(): void
    {
        $this->get('/rozklad-dzvinkiv')
            ->assertOk()
            ->assertSee('1 зміна')
            ->assertSee('2 зміна')
            ->assertSee('08:30 – 09:40')   // перша пара першої зміни
            ->assertSee('17:00 – 18:10')   // остання пара другої зміни
            ->assertSee('Дві зміни')
            ->assertSee('Велика перерва'); // 11:00 → 11:30
    }

    public function test_second_shift_is_hidden_by_setting(): void
    {
        Setting::where('key', BellPeriod::SECOND_SHIFT_SETTING)->update(['value' => '0']);
        $this->forgetCaches();

        $this->assertFalse(BellPeriod::secondShiftEnabled());

        $this->get('/rozklad-dzvinkiv')
            ->assertOk()
            ->assertSee('08:30 – 09:40')
            ->assertSee('Одна зміна')
            ->assertDontSee('2 зміна')
            ->assertDontSee('17:00 – 18:10');
    }

    public function test_edited_times_and_inactive_pairs_reach_the_page(): void
    {
        BellPeriod::where('shift', 1)->where('number', 1)->update(['starts' => '08:00', 'ends' => '09:10']);
        BellPeriod::where('shift', 1)->where('number', 4)->update(['is_active' => false]);
        $this->forgetCaches();

        $this->get('/rozklad-dzvinkiv')
            ->assertOk()
            ->assertSee('08:00 – 09:10')
            ->assertDontSee('12:50 – 14:00');
    }

    public function test_admin_screen_shows_fixed_pairs_of_both_shifts(): void
    {
        $admin = User::firstOrFail(); // створюється сидером

        $this->actingAs($admin)->get(BellSchedule::getUrl())
            ->assertOk()
            ->assertSee('1 зміна')
            ->assertSee('2 зміна')
            ->assertSee('Показувати другу зміну')
            ->assertSee('Показувати у верхньому меню, яка пара йде зараз')
            ->assertDontSee('Створити');
    }

    public function test_admin_form_saves_times_and_toggles(): void
    {
        $this->actingAs(User::firstOrFail());

        Livewire::test(BellSchedule::class)
            ->assertFormSet(['second_shift' => true, 'now_chip' => true])
            ->fillForm(['s1_1_starts' => '08:00', 's1_1_ends' => '09:10', 'second_shift' => false, 'now_chip' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->forgetCaches();

        $this->assertSame('08:00', substr((string) BellPeriod::where('shift', 1)->where('number', 1)->value('starts'), 0, 5));
        $this->assertFalse(BellPeriod::secondShiftEnabled());
        $this->assertFalse(BellPeriod::chipEnabled());

        // Кількість пар фіксована: 4 на кожну зміну.
        $this->assertSame(8, BellPeriod::count());
    }

    public function test_admin_form_rejects_end_before_start(): void
    {
        $this->actingAs(User::firstOrFail());

        Livewire::test(BellSchedule::class)
            ->fillForm(['s1_1_starts' => '10:00', 's1_1_ends' => '09:00'])
            ->call('save')
            ->assertHasFormErrors(['s1_1_ends']);
    }

    public function test_header_chip_is_hidden_by_setting(): void
    {
        $this->get('/')->assertOk()->assertSee('bellChip(', false);

        BellPeriod::setFlag(BellPeriod::NOW_CHIP_SETTING, false);
        $this->forgetCaches();

        $this->get('/')->assertOk()->assertDontSee('bellChip(', false);
    }

    public function test_long_break_is_not_highlighted_statically(): void
    {
        $html = $this->get('/rozklad-dzvinkiv')->assertOk()->getContent();

        // Велика перерва 11:00 → 11:30 стоїть після 2-ї пари першої зміни.
        // У розмітці вона сіра: золото на сторінці означає лише «просто зараз»,
        // і дає його виключно Alpine через isGapNow().
        $this->assertMatchesRegularExpression(
            '/<li class="[^"]*bg-slate-50[^"]*"\s+:class="[^"]*isGapNow\(.[0-9]+.\)[^"]*">\s*Велика перерва/u',
            $html
        );

        // Золотий фон перерви існує лише всередині Alpine-виразу, а не в самому class=""
        $this->assertDoesNotMatchRegularExpression('/<li class="[^"]*bg-gold-50[^"]*"/u', $html);
    }

    public function test_empty_state_when_no_periods(): void
    {
        BellPeriod::query()->delete();
        $this->forgetCaches();

        $this->get('/rozklad-dzvinkiv')
            ->assertOk()
            ->assertSee('Розклад дзвінків ще не налаштовано.');
    }
}
