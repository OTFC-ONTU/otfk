<?php

namespace Tests\Feature;

use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Filament\Resources\PageResource\Pages\ListPages;
use App\Models\LegacyRedirect;
use App\Models\MenuItem;
use App\Models\News;
use App\Models\Page;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Опублікований матеріал не лишає 404: зміна адреси — 301 зі старої на нову (обидві мови, одним
 * переходом), видалення — 301 на розділ/список; видалення сторінки з меню чи системної адреси
 * заблоковано, підсторінки переходять до розділу видаленої.
 */
class PublicUrlRedirectsTest extends TestCase
{
    use RefreshDatabase;

    public function test_slug_change_of_published_page_redirects_old_url_in_one_hop(): void
    {
        $page = Page::create(['title' => 'Стара', 'slug' => 'stara-adresa', 'body' => '<p>Текст</p>', 'is_published' => true]);
        LegacyRedirect::create(['source_path' => '/old/site/page', 'target_url' => '/stara-adresa#rozdil', 'status_code' => 301]);

        $page->update(['slug' => 'nova-adresa']);

        $this->get('/stara-adresa')->assertStatus(301)->assertRedirect('http://localhost/nova-adresa');
        $this->get('/en/stara-adresa')->assertStatus(301)->assertRedirect('http://localhost/en/nova-adresa');
        $this->get('/old/site/page')->assertRedirect('http://localhost/nova-adresa#rozdil');
        $this->get('/nova-adresa')->assertOk();

        // Повернення старої адреси: вона знову жива, ланцюжків і циклів немає
        $page->update(['slug' => 'stara-adresa']);
        $this->get('/stara-adresa')->assertOk();
        $this->get('/nova-adresa')->assertRedirect('http://localhost/stara-adresa');
        $this->get('/old/site/page')->assertRedirect('http://localhost/stara-adresa#rozdil');
    }

    public function test_unpublished_and_future_materials_get_no_redirects(): void
    {
        $draft = Page::create(['title' => 'Чернетка', 'slug' => 'chernetka-a', 'is_published' => false]);
        $draft->update(['slug' => 'chernetka-b']);
        $future = News::create(['title' => 'Майбутня', 'slug' => 'maibutnia-a', 'body' => '<p>x</p>', 'is_published' => true, 'published_at' => now()->addWeek()]);
        $future->update(['slug' => 'maibutnia-b']);
        $this->assertSame(0, LegacyRedirect::count());

        $news = News::create(['title' => 'Новина', 'slug' => 'novyna-a', 'body' => '<p>x</p>', 'is_published' => true, 'published_at' => now()->subDay()]);
        $news->update(['slug' => 'novyna-b']);
        $this->get('/novyny/novyna-a')->assertRedirect('http://localhost/novyny/novyna-b');
    }

    public function test_deleted_materials_redirect_to_section_and_children_stay_in_section(): void
    {
        $hub = Page::create(['title' => 'Розділ', 'slug' => 'rozdil-test', 'is_published' => true]);
        $page = Page::create(['title' => 'Сторінка', 'slug' => 'storinka-test', 'parent_id' => $hub->id, 'is_published' => true]);
        $child = Page::create(['title' => 'Підсторінка', 'slug' => 'pidstorinka-test', 'parent_id' => $page->id, 'is_published' => true]);
        $news = News::create(['title' => 'Новина', 'slug' => 'novyna-vydalena', 'body' => '<p>x</p>', 'is_published' => true, 'published_at' => now()->subDay()]);

        $page->delete();
        $news->delete();

        $this->get('/storinka-test')->assertRedirect('http://localhost/rozdil-test');
        $this->get('/novyny/novyna-vydalena')->assertRedirect('http://localhost/novyny');
        $this->assertSame($hub->id, $child->fresh()->parent_id);
    }

    public function test_delete_action_explains_consequences_and_blocks_menu_and_system_pages(): void
    {
        $this->actingAs(User::firstOrFail());
        $page = Page::create(['title' => 'Звичайна', 'slug' => 'zvychaina', 'is_published' => true]);
        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->mountAction('delete')
            ->assertMountedActionModalSee('/zvychaina перенаправлятиметься на /')
            ->assertMountedActionModalSee('Зняти з публікації');

        $inMenu = Page::create(['title' => 'У меню', 'slug' => 'u-meniu', 'is_published' => true]);
        MenuItem::create(['label' => 'У меню', 'link_type' => 'page', 'page_id' => $inMenu->id, 'is_visible' => true]);
        Livewire::test(EditPage::class, ['record' => $inMenu->getRouteKey()])
            ->callAction('delete');
        $this->assertNotNull($inMenu->fresh());

        $system = Page::query()->firstOrCreate(['slug' => 'abituriyentu'], ['title' => 'Абітурієнту', 'is_published' => true]);
        Livewire::test(EditPage::class, ['record' => $system->getRouteKey()])
            ->assertFormFieldIsDisabled('slug')
            ->callAction('delete');
        $this->assertNotNull($system->fresh());

        Livewire::test(ListPages::class)
            ->selectTableRecords([$inMenu->id, $page->id])
            ->callAction(TestAction::make('delete')->table()->bulk());
        $this->assertNotNull($inMenu->fresh());
        $this->assertNull($page->fresh());
    }

    public function test_incoming_redirects_found_by_normalized_path_and_manual_records_respected(): void
    {
        $page = Page::create(['title' => 'Сторінка', 'slug' => 'probe-a', 'is_published' => true]);
        // Ціль записано зі слешем — модель вважає її тією самою адресою
        LegacyRedirect::create(['source_path' => '/old/probe', 'target_url' => '/probe-a/', 'status_code' => 301]);
        // Ручний запис адміністратора з джерелом, що збігається з новою адресою
        LegacyRedirect::create(['source_path' => '/probe-b', 'target_url' => '/istoriya', 'status_code' => 301, 'note' => 'Вручну']);

        $page->update(['slug' => 'probe-b']);

        $this->get('/old/probe')->assertRedirect('http://localhost/probe-b');
        $this->get('/probe-a')->assertRedirect('http://localhost/probe-b');
        $this->get('/probe-b')->assertOk();
        $this->assertFalse((bool) LegacyRedirect::query()->where('note', 'like', 'Вручну%')->value('is_active'));

        // Після видалення ручний запис повертається з його власною ціллю, а не перезаписується
        $page->delete();
        $this->get('/probe-b')->assertRedirect('http://localhost/istoriya');
    }

    public function test_target_that_redirects_itself_is_replaced_by_final_address(): void
    {
        $section = Page::create(['title' => 'Розділ документів', 'slug' => 'rozdil-dokumentiv', 'body' => '<p>Вступ</p>', 'is_published' => true]);
        $category = \App\Models\DocumentCategory::create(['title' => 'Розділ', 'slug' => 'rozdil', 'page_id' => $section->id]);
        $child = Page::create(['title' => 'Дочірня', 'slug' => 'dochirnia', 'parent_id' => $section->id, 'is_published' => true]);

        $child->delete();

        $this->assertSame('/dokumenty/'.$category->slug, LegacyRedirect::query()->where('source_path', '/dochirnia')->value('target_url'));
    }
}
