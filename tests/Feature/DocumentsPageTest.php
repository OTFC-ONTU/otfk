<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Редизайн публічної інформації: світла шапка з лічильниками, живий фільтр
 * розділів, список документів з пошуком, пагінацією та метаданими файлів.
 */
class DocumentsPageTest extends TestCase
{
    use RefreshDatabase;

    /** Демо-категорії з міграцій прибираємо — сторінки перевіряємо на власному наборі. */
    protected function setUp(): void
    {
        parent::setUp();

        Document::query()->delete();
        DocumentCategory::query()->delete();
    }

    private function makeCategory(string $title, string $slug, int $documents = 0, int $sort = 0): DocumentCategory
    {
        $category = DocumentCategory::create([
            'title' => $title,
            'slug' => $slug,
            'sort_order' => $sort,
        ]);

        for ($i = 1; $i <= $documents; $i++) {
            Document::create([
                'document_category_id' => $category->id,
                'title' => "{$title}: документ №{$i}",
                'file_path' => "documents/{$slug}/{$i}.pdf",
                'published_at' => now()->subDays($i),
                'sort_order' => $i,
                'is_published' => true,
            ]);
        }

        return $category;
    }

    public function test_index_has_light_header_with_counters(): void
    {
        $this->makeCategory('Нормативна база', 'normatyvna-baza', 3);
        $this->makeCategory('Звіти', 'zvity', 2, 1);

        $this->get('/dokumenty')
            ->assertOk()
            // Світла шапка розділу — як на новинах, структурі та спеціальностях
            ->assertSee('border-b border-slate-200/70 bg-slate-50/80', false)
            ->assertDontSee('<section class="bg-brand-950">', false)
            ->assertSee('Публічна інформація')
            // Лічильники розділів і документів — числа й слова в сусідніх елементах
            ->assertSeeInOrder(['2', 'категорії', '5', 'документів у відкритому доступі']);
    }

    public function test_index_lists_categories_with_counts_and_filter_pills(): void
    {
        $this->makeCategory('Нормативна база', 'normatyvna-baza', 3);
        $this->makeCategory('Аудиторний фонд', 'audytornyy-fond');

        $response = $this->get('/dokumenty')->assertOk();

        $response->assertSee('Нормативна база')
            ->assertSee('3 документи')
            // Порожня категорія лишається у списку, але без бейджа PDF
            ->assertSee('Аудиторний фонд')
            ->assertSee('Незабаром')
            // Живий пошук і фільтр розділів на Alpine
            ->assertSee('Пошук розділу документів…', false)
            ->assertSee('З документами')
            ->assertSee('Порожні')
            ->assertSee(route('documents.category', 'normatyvna-baza'), false);
    }

    public function test_index_shows_contact_block_with_call_to_action(): void
    {
        $this->makeCategory('Звіти', 'zvity', 1);

        $this->get('/dokumenty')
            ->assertOk()
            ->assertSee('Не знайшли документ?')
            ->assertSee(route('contacts'), false)
            ->assertSee(route('faq'), false);
    }

    public function test_category_page_has_light_header_with_document_counter(): void
    {
        $this->makeCategory('Нормативна база', 'normatyvna-baza', 3);

        $this->get('/dokumenty/normatyvna-baza')
            ->assertOk()
            ->assertSee('border-b border-slate-200/70 bg-slate-50/80', false)
            ->assertDontSee('<section class="bg-brand-950">', false)
            ->assertSee('Нормативна база')
            ->assertSee('3 документи')
            ->assertSee('Формат PDF');
    }

    public function test_category_page_lists_documents_with_metadata_and_download(): void
    {
        $this->makeCategory('Звіти', 'zvity', 2);

        $this->get('/dokumenty/zvity')
            ->assertOk()
            ->assertSee('Звіти: документ №1')
            ->assertSee('Показано 1–2 із 2 документів')
            ->assertSee('PDF')
            ->assertSee('Завантажити')
            ->assertSee('Переглянути');
    }

    public function test_category_sidebar_lists_all_sections_with_current_marked(): void
    {
        $this->makeCategory('Нормативна база', 'normatyvna-baza', 2);
        $this->makeCategory('Договори', 'dohovory', 1, 1);

        $this->get('/dokumenty/dohovory')
            ->assertOk()
            ->assertSee('Усі розділи')
            ->assertSee('Нормативна база')
            ->assertSee('aria-current="page"', false);
    }

    public function test_category_page_paginates_by_twenty(): void
    {
        $this->makeCategory('Нормативна база', 'normatyvna-baza', 25);

        $this->get('/dokumenty/normatyvna-baza')
            ->assertOk()
            ->assertSee('Показано 1–20 із 25 документів')
            ->assertSee('Нормативна база: документ №1')
            ->assertDontSee('Нормативна база: документ №21');

        $this->get('/dokumenty/normatyvna-baza?page=2')
            ->assertOk()
            ->assertSee('Нормативна база: документ №21');
    }

    public function test_category_search_ignores_case_of_cyrillic_titles(): void
    {
        $category = $this->makeCategory('Нормативна база', 'normatyvna-baza', 2);

        Document::create([
            'document_category_id' => $category->id,
            'title' => 'Положення про стипендіальну комісію',
            'file_path' => 'documents/normatyvna-baza/stypendii.pdf',
            'published_at' => now(),
            'is_published' => true,
        ]);

        $this->get('/dokumenty/normatyvna-baza?q=' . urlencode('положення про'))
            ->assertOk()
            ->assertSee('Положення про стипендіальну комісію')
            ->assertDontSee('Нормативна база: документ №1')
            ->assertSee('Скинути пошук');
    }

    public function test_category_search_without_results_shows_empty_state(): void
    {
        $this->makeCategory('Звіти', 'zvity', 2);

        $this->get('/dokumenty/zvity?q=' . urlencode('невідомий документ'))
            ->assertOk()
            ->assertSee('документів не знайдено')
            ->assertSee('Показати всі документи');
    }

    public function test_empty_category_shows_placeholder(): void
    {
        $this->makeCategory('Аудиторний фонд', 'audytornyy-fond');

        $this->get('/dokumenty/audytornyy-fond')
            ->assertOk()
            ->assertSee('Документи цієї категорії незабаром буде додано.');
    }
}
