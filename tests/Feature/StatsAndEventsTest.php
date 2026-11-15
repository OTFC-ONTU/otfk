<?php

namespace Tests\Feature;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatsAndEventsTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_shows_stats(): void
    {
        // Лічильники показуються фінальними значеннями, без анімації відліку
        $this->get('/')
            ->assertOk()
            ->assertSee('Студентів')
            ->assertSee('Спеціальностей');
    }

    public function test_events_page_renders_empty_state(): void
    {
        $this->get('/podiyi')
            ->assertOk()
            ->assertSee('Запланованих подій поки немає');
    }

    public function test_upcoming_event_appears_on_home_and_events_page(): void
    {
        Event::create([
            'title' => 'День відкритих дверей',
            'description' => 'Запрошуємо майбутніх студентів',
            'location' => 'Актова зала',
            'starts_at' => now()->addDays(7)->setTime(11, 0),
            'is_published' => true,
        ]);

        $this->get('/')->assertSee('Найближчі події')->assertSee('День відкритих дверей');
        $this->get('/podiyi')->assertSee('День відкритих дверей')->assertSee('Актова зала');
    }

    public function test_home_event_card_with_details_link_is_clickable(): void
    {
        $internal = Event::create([
            'title' => 'Олімпіада з програмування',
            'starts_at' => now()->addDays(3)->setTime(10, 0),
            'url' => url('/novyny/olimpiada'),
            'is_published' => true,
        ]);
        Event::create([
            'title' => 'Зовнішній форум',
            'starts_at' => now()->addDays(4)->setTime(10, 0),
            'url' => 'https://example.org/forum',
            'is_published' => true,
        ]);
        Event::create([
            'title' => 'Подія без посилання',
            'starts_at' => now()->addDays(5)->setTime(10, 0),
            'url' => 'javascript:alert(1)',
            'is_published' => true,
        ]);

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertSame(2, substr_count($html, 'data-event-link'));
        $this->assertMatchesRegularExpression('~<a\s+href="'.preg_quote(url('/novyny/olimpiada'), '~').'" data-event-link\s+class=~', $html);
        $this->assertMatchesRegularExpression('~<a\s+href="https://example.org/forum" data-event-link\s+target="_blank" rel="noopener"~', $html);
        $this->assertStringNotContainsString('javascript:alert', $html);
        $this->assertStringContainsString('Подія без посилання', $html);

        // На /en внутрішнє посилання веде на англійську версію
        $this->assertStringContainsString('href="'.url('/en/novyny/olimpiada').'"', $this->get('/en')->getContent());
    }

    public function test_event_ics_download_and_calendar_buttons(): void
    {
        $event = Event::create([
            'title' => 'День відкритих дверей; запрошуємо',
            'description' => 'Знайомство, екскурсія',
            'location' => 'Актова зала',
            'starts_at' => now()->addDays(5)->setTime(11, 0),
            'is_published' => true,
        ]);

        // Кнопки на сторінці подій
        $this->get('/podiyi')
            ->assertSee('Google Календар')
            ->assertSee('calendar.google.com/calendar/render', escape: false)
            ->assertSee(route('events.ics', $event), escape: false);

        // Файл .ics: заголовки + структура + екранування «;»
        $resp = $this->get(route('events.ics', $event));
        $resp->assertOk()->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
        $body = $resp->getContent();
        $this->assertStringContainsString('BEGIN:VCALENDAR', $body);
        $this->assertStringContainsString('SUMMARY:День відкритих дверей\; запрошуємо', $body);
        $this->assertStringContainsString('LOCATION:Актова зала', $body);
        $this->assertStringContainsString('DTSTART:', $body);

        // Неопублікована подія — 404
        $event->update(['is_published' => false]);
        $this->get(route('events.ics', $event))->assertNotFound();
    }

    public function test_past_event_listed_in_past_section(): void
    {
        Event::create([
            'title' => 'Минула конференція',
            'starts_at' => now()->subDays(10),
            'is_published' => true,
        ]);

        $this->get('/podiyi')->assertSee('Минулі події')->assertSee('Минула конференція');
        $this->get('/')->assertDontSee('Минула конференція');
    }
}
