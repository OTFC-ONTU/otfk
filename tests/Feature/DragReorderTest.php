<?php

namespace Tests\Feature;

use App\Filament\Resources\BannerResource\Pages\ListBanners;
use App\Filament\Resources\MenuItemResource\Pages\ListMenuItems;
use App\Filament\Resources\NewsResource\Pages\ListNews;
use App\Filament\Resources\StaffResource;
use App\Models\Banner;
use App\Models\Gallery;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Порядок в адмінці — перетягуванням: переставлені записи обмінюються наявними номерами
 * (приховані пошуком/фільтром не зсуваються), збереження через модель скидає кеші,
 * новий запис стає в кінець, а міграція нумерує повтори в порядку, який показує сайт.
 */
class DragReorderTest extends TestCase
{
    use RefreshDatabase;

    public function test_reorder_button_has_text_and_short_lists_have_no_selection(): void
    {
        $this->actingAs(User::firstOrFail());

        // Перестановка — окремим режимом (без випадкових зсувів), але кнопка з підписом; «Готово» завершує режим
        $list = Livewire::test(ListBanners::class)->assertSee('Змінити порядок');
        // Короткі впорядковані списки без масових дій — без квадратиків вибору рядків
        $this->assertFalse($list->instance()->getTable()->isSelectionEnabled());
        $list->call('toggleTableReordering')->assertSee('Готово');

        $this->assertFalse(Livewire::test(ListMenuItems::class)->instance()->getTable()->isSelectionEnabled());
        $this->assertTrue(Livewire::test(ListNews::class)->instance()->getTable()->isSelectionEnabled());
    }

    public function test_reorder_swaps_existing_positions_of_shown_records_only(): void
    {
        $this->actingAs(User::firstOrFail());
        Banner::query()->delete();
        $a = Banner::create(['title' => 'A', 'sort_order' => 10, 'is_published' => true]);
        $b = Banner::create(['title' => 'B', 'sort_order' => 20, 'is_published' => true]);
        $c = Banner::create(['title' => 'C', 'sort_order' => 30, 'is_published' => true]);
        $updatedAt = $a->fresh()->updated_at;

        // Показано лише A і C (наприклад, пошук) — C перетягнули вище A
        Livewire::test(ListBanners::class)->call('reorderTable', [(string) $c->id, (string) $a->id]);

        $this->assertSame(10, $c->fresh()->sort_order);
        $this->assertSame(20, $b->fresh()->sort_order);
        $this->assertSame(30, $a->fresh()->sort_order);
        $this->assertEquals($updatedAt, $a->fresh()->updated_at);
        $this->assertSame(['C', 'B', 'A'], Banner::query()->ordered()->pluck('title')->all());
    }

    public function test_reorder_saves_through_model_and_flushes_menu_cache(): void
    {
        $this->actingAs(User::firstOrFail());
        $roots = MenuItem::whereNull('parent_id')->orderBy('sort_order')->get();
        $this->assertGreaterThan(1, $roots->count());
        Cache::put('menu.navigation', 'stale', 600);

        $keys = $roots->pluck('id')->map(fn ($id) => (string) $id)->all();
        [$keys[0], $keys[1]] = [$keys[1], $keys[0]];
        Livewire::test(ListMenuItems::class)->call('reorderTable', $keys);

        $this->assertFalse(Cache::has('menu.navigation'));
        $this->assertSame((int) $keys[0], MenuItem::whereNull('parent_id')->orderBy('sort_order')->value('id'));
    }

    public function test_new_record_goes_to_the_end_and_form_has_no_order_field(): void
    {
        $max = (int) \App\Models\Faq::max('sort_order');
        $faq = \App\Models\Faq::create(['question' => 'Нове питання?', 'answer' => 'Відповідь', 'is_active' => true]);
        $this->assertSame($max + 1, $faq->sort_order);

        // Стрічки «новіше вгорі» (банери, альбоми, відео, документи) — новий запис першим, як раніше за датою
        $min = (int) Banner::min('sort_order');
        $banner = Banner::create(['title' => 'Новий', 'is_published' => true]);
        $this->assertSame($min - 1, $banner->sort_order);
        $this->assertSame($banner->id, Banner::query()->ordered()->value('id'));
        $gallery = Gallery::create(['title' => 'Новий альбом', 'slug' => 'novyi-albom-test', 'published_at' => now(), 'is_published' => true]);
        $this->assertSame($gallery->id, Gallery::query()->ordered()->value('id'));

        $this->actingAs(User::firstOrFail());
        $this->get(StaffResource::getUrl('create'))->assertOk()->assertDontSee('data.sort_order', false);
    }

    public function test_migration_numbers_duplicates_in_public_order(): void
    {
        Gallery::query()->delete();
        $old = Gallery::create(['title' => 'Старий', 'slug' => 'staryi', 'sort_order' => 0, 'published_at' => '2024-01-01', 'is_published' => true]);
        $new = Gallery::create(['title' => 'Новий', 'slug' => 'novyi', 'sort_order' => 0, 'published_at' => '2025-01-01', 'is_published' => true]);
        $first = Gallery::create(['title' => 'Перший', 'slug' => 'pershyi', 'sort_order' => -1, 'published_at' => '2020-01-01', 'is_published' => true]);
        $before = Gallery::query()->ordered()->pluck('id')->all();

        (require database_path('migrations/2026_10_09_120000_normalize_sort_order_for_drag_reorder.php'))->up();

        $this->assertSame($before, Gallery::query()->ordered()->pluck('id')->all());
        $this->assertSame([1, 2, 3], [$first->fresh()->sort_order, $new->fresh()->sort_order, $old->fresh()->sort_order]);
    }

    public function test_tables_have_no_automatic_key_sort_that_breaks_group_by_on_mysql(): void
    {
        $this->actingAs(User::firstOrFail());
        $widget = Livewire::test(\App\Filament\Widgets\TopPages::class)->instance();

        $this->assertFalse($widget->getTable()->hasDefaultKeySort());
        $this->assertStringNotContainsString('"site_visits"."id" asc', $widget->getFilteredSortedTableQuery()->toSql());
    }
}
