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
}
