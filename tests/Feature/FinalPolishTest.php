<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\News;
use App\Models\SiteVisit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinalPolishTest extends TestCase
{
    use RefreshDatabase;

    // ---- Живий пошук ----

    public function test_suggest_returns_grouped_results(): void
    {
        News::create([
            'title' => 'Унікальний турнір з кібербезпеки',
            'body' => '<p>текст</p>',
            'published_at' => now()->subDay(),
            'is_published' => true,
        ]);

        // Кирилицю в query кодуємо явно: сирі не-ASCII байти в URL тестового
        // клієнта губляться на частині локальних PHP-збірок (флак у тесті).
        $this->getJson('/poshuk/pidkazky?q=' . rawurlencode('кібербезпеки'))
            ->assertOk()
            ->assertJsonPath('results.0.group', 'Новина')
            ->assertJsonPath('results.0.title', 'Унікальний турнір з кібербезпеки');
    }

    public function test_suggest_requires_two_characters(): void
    {
        $this->getJson('/poshuk/pidkazky?q=а')
            ->assertOk()
            ->assertJsonPath('total', 0);
    }

    // ---- SEO-пакет ----

    public function test_sitemap_includes_new_sections(): void
    {
        $body = $this->get('/sitemap.xml')->assertOk()->getContent();

        foreach (['/podiyi', '/faq', '/rozklad-dzvinkiv'] as $path) {
            $this->assertStringContainsString(url($path), $body);
        }
    }

    public function test_news_page_has_newsarticle_schema(): void
    {
        $news = News::create([
            'title' => 'Новина зі схемою',
            'body' => '<p>текст</p>',
            'published_at' => now()->subDay(),
            'is_published' => true,
        ]);

        $this->get(route('news.show', $news))
            ->assertSee('"@type":"NewsArticle"', escape: false);
    }

    public function test_events_page_has_event_schema(): void
    {
        Event::create([
            'title' => 'Подія зі схемою',
            'starts_at' => now()->addDays(3),
            'is_published' => true,
        ]);

        $this->get('/podiyi')->assertSee('"@type":"Event"', escape: false);
    }

    public function test_specialty_page_has_course_schema(): void
    {
        $specialty = \App\Models\Specialty::published()->first();

        if (! $specialty) {
            $this->markTestSkipped('Сидер не створив спеціальностей.');
        }

        $this->get(route('specialties.show', $specialty))
            ->assertSee('"@type":"Course"', escape: false);
    }

    // ---- Статистика відвідувань ----

    public function test_visits_are_tracked_for_public_pages(): void
    {
        $this->get('/');

        $this->assertDatabaseHas('site_visits', ['path' => '/', 'hits' => 1]);
        $this->assertDatabaseHas('site_visits', ['path' => SiteVisit::VISITS_PATH, 'hits' => 1]);

        // Друга сторінка тієї ж сесії: перегляд +, візит той самий
        $this->get('/novyny');
        $this->assertDatabaseHas('site_visits', ['path' => '/novyny']);
        $this->assertSame(1, (int) SiteVisit::where('path', SiteVisit::VISITS_PATH)->sum('hits'));
    }

    public function test_admin_and_bots_are_not_tracked(): void
    {
        $this->get('/admin'); // редірект на логін — не 200, не трекається

        $this->withHeaders(['User-Agent' => 'Googlebot/2.1'])->get('/');

        $this->assertDatabaseMissing('site_visits', ['path' => '/admin']);
        $this->assertDatabaseMissing('site_visits', ['path' => '/']);
    }
}
