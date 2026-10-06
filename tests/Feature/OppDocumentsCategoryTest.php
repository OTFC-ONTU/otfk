<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\MenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Відновлення розділу «Освітньо-професійні програми» зі старого сайту:
 * окрема категорія документів замість дубля посилання на /spetsialnosti.
 */
class OppDocumentsCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_opp_category_exists_and_page_renders(): void
    {
        $category = DocumentCategory::where('slug', 'osvitno-profesiyni-prohramy')->first();

        $this->assertNotNull($category, 'Міграція має створювати категорію документів для ОПП');
        $this->assertSame('Освітньо-професійні програми', $category->title);

        Document::create([
            'document_category_id' => $category->id,
            'title' => 'ОПП спеціальність: F2 «Інженерія програмного забезпечення»',
            'file_path' => 'documents/osvitno-profesiyni-prohramy/test.pdf',
            'published_at' => now(),
            'sort_order' => 1,
            'is_published' => true,
        ]);

        $this->get('/dokumenty/osvitno-profesiyni-prohramy')
            ->assertOk()
            ->assertSee('Освітньо-професійні програми')
            ->assertSee('Інженерія програмного забезпечення');
    }

    public function test_migration_repoints_menu_item_from_specialties_duplicate(): void
    {
        // Стан зі старого сидера: обидва пункти вели на /spetsialnosti.
        $item = MenuItem::create([
            'label' => 'Освітньо-професійні програми',
            'link_type' => 'url',
            'url' => '/spetsialnosti',
            'sort_order' => 2,
            'is_visible' => true,
        ]);

        $migration = include database_path('migrations/2026_08_28_190000_seed_opp_document_category_and_menu.php');
        $migration->up();

        $this->assertSame('/dokumenty/osvitno-profesiyni-prohramy', $item->fresh()->url);
    }
}
