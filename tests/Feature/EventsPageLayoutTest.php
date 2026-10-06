<?php

namespace Tests\Feature;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Контракти оформлення сторінки подій після редизайну. */
class EventsPageLayoutTest extends TestCase
{
    use RefreshDatabase;

    private function event(array $attributes = []): Event
    {
        return Event::create(array_merge([
            'title' => 'Подія',
            'starts_at' => now()->addDays(5)->setTime(11, 0),
            'is_published' => true,
        ], $attributes));
    }

    public function test_light_page_header_with_breadcrumbs_and_counters(): void
    {
        $this->event(['title' => 'День відкритих дверей']);
        $this->event(['title' => 'Майстер-клас', 'starts_at' => now()->addDays(9)->setTime(10, 0)]);
        $this->event(['title' => 'Минула зустріч', 'starts_at' => now()->subDays(10)]);

        $this->get('/podiyi')
            ->assertOk()
            ->assertSee('Події коледжу')
            ->assertSee('Навігаційний ланцюжок', escape: false)
            ->assertSee('2 події попереду')
            ->assertSee('1 подія в архіві');
    }

    public function test_nearest_event_is_featured_with_countdown(): void
    {
        $this->event([
            'title' => 'Найближчий захід',
            'description' => 'Опис заходу',
            'location' => 'Актова зала',
            'starts_at' => now()->addDay()->setTime(11, 0),
            'ends_at' => now()->addDay()->setTime(13, 0),
        ]);

        $this->get('/podiyi')
            ->assertSee('Найближча подія')
            ->assertSee('Завтра')
            ->assertSee('Найближчий захід')
            ->assertSee('Актова зала')
            ->assertSee('11:00');
    }

    public function test_every_event_offers_google_calendar_and_ics(): void
    {
        $first = $this->event(['title' => 'Перша подія', 'starts_at' => now()->addDays(2)->setTime(9, 0)]);
        $second = $this->event(['title' => 'Друга подія', 'starts_at' => now()->addDays(4)->setTime(9, 0)]);

        $response = $this->get('/podiyi');

        foreach ([$first, $second] as $event) {
            $response->assertSee(route('events.ics', $event), escape: false);
        }

        $response->assertSee('Завантажити .ics')->assertSee('Google Календар');
    }

    public function test_empty_state_leads_to_news(): void
    {
        $this->get('/podiyi')
            ->assertSee('Запланованих подій поки немає.')
            ->assertSee('Перейти до новин')
            ->assertSee(route('news.index'), escape: false);
    }

    public function test_past_events_render_without_calendar_buttons(): void
    {
        $past = $this->event(['title' => 'Минула конференція', 'starts_at' => now()->subDays(30)]);

        $this->get('/podiyi')
            ->assertSee('Минулі події')
            ->assertSee('Минула конференція')
            ->assertDontSee(route('events.ics', $past), escape: false);
    }

    public function test_page_keeps_final_call_to_action(): void
    {
        $this->get('/podiyi')
            ->assertSee('Будьте в курсі всіх подій коледжу')
            ->assertSee(route('contacts'), escape: false);
    }
}
