<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\News;
use App\Models\Page;
use App\Models\Specialty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Редизайн пошуку: світла шапка з полем і чипами-фільтрами, стани порожнього
 * запиту / нічого не знайдено, пагінація та регістронезалежний пошук кирилицею.
 */
class SearchPageTest extends TestCase
{
    use RefreshDatabase;

    private function makeNews(string $title, int $daysAgo = 1): News
    {
        return News::create([
            'title' => $title,
            'body' => '<p>текст</p>',
            'published_at' => now()->subDays($daysAgo),
            'is_published' => true,
        ]);
    }

    public function test_empty_query_shows_hint_and_sections(): void
    {
        $this->get('/poshuk')
            ->assertOk()
            // Світла шапка — як на решті внутрішніх сторінок
            ->assertSee('border-b border-slate-200/70 bg-slate-50/80', false)
            ->assertSee('Що вас цікавить?', false)
            ->assertSee('Введіть запит у поле вище')
            // Перелік того, що взагалі шукається
            ->assertSee('Спеціальності')
            ->assertSee('Популярні розділи');
    }

    public function test_short_query_asks_for_two_characters(): void
    {
        $this->get('/poshuk?q=' . rawurlencode('а'))
            ->assertOk()
            ->assertSee('Введіть щонайменше два символи');
    }

    public function test_finds_results_regardless_of_cyrillic_case(): void
    {
        $this->makeNews('Положення про приймальну комісію');

        // У SQLite LIKE не бачить регістру кирилиці (Gotcha 21) — фільтр іде в PHP
        $this->get('/poshuk?q=' . rawurlencode('положення'))
            ->assertOk()
            // Збіг у назві підсвічується, тому перевіряємо нерозірвану частину назви
            ->assertSee('про приймальну комісію', false)
            ->assertSee('<mark class="rounded bg-gold-100', false)
            ->assertSee('Результати за запитом', false);
    }

    public function test_type_chips_count_and_filter_results(): void
    {
        $this->makeNews('Турнір з кібербезпеки');
        Specialty::create([
            'title' => 'Кібербезпека',
            'slug' => 'kiberbezpeka-test',
            'is_published' => true,
        ]);

        $all = $this->get('/poshuk?q=' . rawurlencode('кібербез'))->assertOk();
        $all->assertSee('Турнір з', false);
        $all->assertSee('пека', false);
        $all->assertSee('Спеціальності');
        $all->assertSee('Новини');

        // Фільтр за типом лишає тільки свою групу
        $this->get('/poshuk?q=' . rawurlencode('кібербез') . '&type=specialties')
            ->assertOk()
            ->assertSee('пека', false)
            ->assertDontSee('Турнір з', false)
            ->assertSee('Показати всі типи');
    }

    public function test_documents_and_pages_are_searchable(): void
    {
        $category = DocumentCategory::create(['title' => 'Звіти', 'slug' => 'zvity-test']);
        Document::create([
            'document_category_id' => $category->id,
            'title' => 'Звіт про фінансову діяльність',
            'file_path' => 'documents/zvit.pdf',
            'is_published' => true,
        ]);
        Page::create([
            'title' => 'Бібліотека коледжу',
            'slug' => 'biblioteka-test',
            'body' => '<p>текст</p>',
            'is_published' => true,
        ]);

        $this->get('/poshuk?q=' . rawurlencode('звіт'))
            ->assertOk()
            ->assertSee('про фінансову діяльність', false);

        $this->get('/poshuk?q=' . rawurlencode('бібліотека'))
            ->assertOk()
            ->assertSee(url('/biblioteka-test'), false);
    }

    public function test_no_results_state_offers_next_steps(): void
    {
        $this->get('/poshuk?q=' . rawurlencode('щосьчогонемає'))
            ->assertOk()
            ->assertSee('нічого не знайдено', false)
            ->assertSee('Запитати в коледжу')
            ->assertSee('Розділи сайту');
    }

    public function test_results_are_paginated(): void
    {
        for ($i = 1; $i <= 14; $i++) {
            $this->makeNews("Фестиваль науки №{$i}", $i);
        }

        // Найстаріша новина йде останньою — має опинитися на другій сторінці
        $this->makeNews('Фестиваль науки найдавніший', 30);

        $first = $this->get('/poshuk?q=' . rawurlencode('фестиваль'))->assertOk();
        $first->assertSee('із 15', false);
        $first->assertSee('Показано 1–12', false);
        $first->assertDontSee('найдавніший', false);

        $this->get('/poshuk?q=' . rawurlencode('фестиваль') . '&page=2')
            ->assertOk()
            ->assertSee('найдавніший', false);
    }

    public function test_suggest_still_groups_results(): void
    {
        $this->makeNews('Унікальний турнір з робототехніки');

        $this->getJson('/poshuk/pidkazky?q=' . rawurlencode('РОБОТОТЕХНІКИ'))
            ->assertOk()
            ->assertJsonPath('results.0.group', 'Новина')
            ->assertJsonPath('results.0.title', 'Унікальний турнір з робототехніки');
    }

    public function test_huge_or_array_query_is_trimmed_and_does_not_break_search(): void
    {
        $this->makeNews('Олімпіада з програмування');
        $huge = 'олімпіада'.str_repeat('я', 20000);

        $html = $this->get('/poshuk?q='.urlencode($huge))->assertOk()->getContent();
        $this->assertStringNotContainsString(str_repeat('я', \App\Support\SearchQuery::MAX_LENGTH + 1), $html);
        $this->assertStringContainsString('maxlength="'.\App\Support\SearchQuery::MAX_LENGTH.'"', $html);

        $this->get('/poshuk?q[]=олімпіада&type[]=news')->assertOk();
        $this->getJson('/poshuk/pidkazky?q[]=олімпіада')->assertOk()->assertJsonPath('total', 0);
        $this->get('/poshuk?q='.urlencode('олімпіада'.str_repeat(' ', 300).'x'))->assertOk();
        $this->getJson('/poshuk/pidkazky?q='.urlencode($huge))->assertOk()->assertJsonPath('total', 0);
    }

    public function test_search_page_is_rate_limited(): void
    {
        foreach (range(1, 30) as $i) {
            $this->get('/poshuk?q=тест')->assertOk();
        }
        $this->get('/poshuk?q=тест')->assertStatus(429);
    }

    public function test_type_chips_are_translated_and_capitalized_in_both_locales(): void
    {
        $this->makeNews('Кошторис новина');
        Page::create(['title' => 'Кошторис сторінка', 'body' => '<p>т</p>', 'is_published' => true]);
        Specialty::create(['title' => 'Кошторис спеціальність', 'code' => '999', 'is_published' => true]);
        $category = DocumentCategory::create(['title' => 'Фінанси']);
        Document::create(['title' => 'Кошторис 2026', 'document_category_id' => $category->id, 'is_published' => true]);

        $uk = $this->get('/poshuk?q='.urlencode('кошторис'))->assertOk();
        foreach (['Новини', 'Сторінки', 'Спеціальності', 'Документи'] as $chip) {
            $uk->assertSee($chip);
        }
        $uk->assertDontSee('public.')->assertDontSee('feature.')->assertDontSee('>сторінки', false);

        $this->get('/en/poshuk?q='.urlencode('Кошторис'))->assertOk()
            ->assertSee('Pages')->assertSee('Documents')->assertSee('Specialties')
            ->assertDontSee('public.')->assertDontSee('feature.');
    }

    public function test_query_and_titles_are_escaped_and_highlight_does_not_break_entities(): void
    {
        $this->makeNews('R&D <b>лабораторія</b> "amp"');
        $payloads = ['"><script>alert(1)</script>', '<img src=x onerror=alert(1)>', "' OR 1=1 --"];

        foreach ($payloads as $payload) {
            $html = $this->get('/poshuk?q='.urlencode($payload))->assertOk()->getContent();
            $this->assertStringNotContainsString($payload, $html);
            $this->assertStringContainsString(e($payload), $html);
            $this->getJson('/poshuk/pidkazky?q='.urlencode($payload))->assertOk()->assertJsonPath('total', 0);
        }

        // Запит «amp» не вклинюється в &amp;, а заголовок з тегами лишається текстом.
        $html = $this->get('/poshuk?q=amp')->assertOk()->getContent();
        $this->assertStringContainsString('R&amp;D &lt;b&gt;лабораторія&lt;/b&gt; &quot;<mark class="rounded bg-gold-100 px-0.5 text-brand-950">amp</mark>&quot;', $html);
        $this->assertStringNotContainsString('&<mark', $html);

        $this->get('/poshuk?q=test&type='.urlencode("news' OR 1=1"))->assertOk();
        $this->get('/poshuk?q=test&page='.urlencode('1 OR 1=1'))->assertOk();
    }
}
