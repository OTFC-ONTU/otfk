<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Смоук-тест списків адмінки після UX-правок Етапу 1 (ADMIN-UX-PLAN.md):
 * перетягування порядку, тумблери публікації, фільтри та порожні стани
 * не повинні ламати рендер жодної List-сторінки ресурсів.
 */
class AdminTablesTest extends TestCase
{
    use RefreshDatabase;

    /** Слаги List-сторінок усіх ресурсів адмінки. */
    private const RESOURCE_PATHS = [
        'pages', 'menu-items', 'news', 'news-categories', 'videos', 'banners',
        'events', 'faqs', 'stat-items', 'quiz-questions', 'quick-links',
        'galleries', 'documents', 'document-categories', 'specialties',
        'programs', 'departments', 'staff',
    ];

    public function test_all_resource_list_pages_render_for_admin(): void
    {
        $this->actingAs(User::factory()->create());

        foreach (self::RESOURCE_PATHS as $path) {
            $this->get('/admin/' . $path)->assertOk();
        }
    }

    public function test_menu_items_list_shows_level_tabs(): void
    {
        // Меню редагується вкладками рівнів: «Верхній рівень» + вкладка
        // підпунктів на кожен головний пункт (замість однієї плоскої таблиці).
        $root = \App\Models\MenuItem::create(['label' => 'Тестовий пункт меню', 'link_type' => 'url', 'url' => '#']);
        \App\Models\MenuItem::create(['label' => 'Тестовий підпункт', 'link_type' => 'url', 'url' => '#', 'parent_id' => $root->id]);

        $this->actingAs(User::factory()->create())
            ->get('/admin/menu-items')
            ->assertOk()
            ->assertSee('Верхній рівень')
            ->assertSee('Тестовий пункт меню');
    }

    public function test_quick_links_resource_lists_only_home_tiles(): void
    {
        // Партнери підвалу редагуються у «Підвал і вигляд», а не в цьому ресурсі.
        \App\Models\QuickLink::create(['location' => 'home_tile', 'title' => 'Тестова плитка', 'url' => '/']);
        \App\Models\QuickLink::create(['location' => 'footer_partner', 'title' => 'Тестовий партнер підвалу', 'url' => 'https://example.com']);

        $this->actingAs(User::factory()->create())
            ->get('/admin/quick-links')
            ->assertOk()
            ->assertSee('Тестова плитка')
            ->assertDontSee('Тестовий партнер підвалу');
    }

    public function test_news_table_has_no_publish_toggle_column(): void
    {
        // Тумблер публікації новин у таблиці свідомо відсутній:
        // NewsObserver шле автопост у Telegram при «оживленні» новини,
        // тож перемикання доступне лише у формі редагування.
        $columns = \App\Filament\Resources\NewsResource::table(
            \Filament\Tables\Table::make(new class extends \Filament\Resources\Pages\ListRecords
            {
                protected static string $resource = \App\Filament\Resources\NewsResource::class;
            })
        )->getColumns();

        $this->assertNotInstanceOf(\Filament\Tables\Columns\ToggleColumn::class, $columns['is_published']);
    }
}
