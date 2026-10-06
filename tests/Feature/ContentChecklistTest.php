<?php

namespace Tests\Feature;

use App\Filament\Resources\PageResource;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentChecklistTest extends TestCase
{
    use RefreshDatabase;

    public function test_fill_links_resolve_not_404(): void
    {
        $admin = User::firstOrFail();
        $stub = Page::create([
            'title' => 'Тестова заглушка',
            'slug' => 'testova-zaglushka-chk',
            'body' => '<p>дуже коротко</p>',
            'is_published' => true,
        ]);

        // Filament біндить ці моделі по slug — посилання має бути slug-based, а не по id.
        $editUrl = PageResource::getUrl('edit', ['record' => $stub]);

        $this->actingAs($admin)->get('/admin/content-checklist')
            ->assertOk()
            ->assertSee($editUrl, escape: false);

        // І саме воно відкривається (раніше id-URL давав 404).
        $this->actingAs($admin)->get(parse_url($editUrl, PHP_URL_PATH))->assertOk();
    }

    public function test_route_tile_and_hub_pages_are_not_counted_as_stubs(): void
    {
        // Плитка: слаг перехоплюється явним маршрутом — тіло не рендериться ніколи.
        Page::firstOrCreate(['slug' => 'rozklad-dzvinkiv'], [
            'title' => 'Розклад дзвінків',
            'is_published' => true,
        ]);

        // Хаб: сторінка з опублікованою дочірньою рендерить плитки розділу без тіла.
        $hub = Page::create(['title' => 'Тестовий хаб', 'slug' => 'testovyi-khab-chk', 'is_published' => true]);
        Page::create([
            'title' => 'Дочірня заглушка',
            'slug' => 'dochirnya-zaglushka-chk',
            'parent_id' => $hub->id,
            'is_published' => true,
        ]);

        $labels = collect((new \App\Filament\Pages\ContentChecklist)->stubPages())->pluck('label');

        $this->assertNotContains('Розклад дзвінків', $labels);
        $this->assertNotContains('Тестовий хаб', $labels);
        // Порожня дочірня сторінка (не хаб і не плитка) — лишається в списку.
        $this->assertContains('Дочірня заглушка', $labels);
    }

    public function test_checklist_page_renders_for_admin(): void
    {
        $admin = User::firstOrFail(); // створюється сидером

        // Рендер сторінки проганяє всі п'ять запитів-перевірок — ловить будь-яку помилку в них.
        $this->actingAs($admin)->get('/admin/content-checklist')
            ->assertOk()
            ->assertSee('Що ще наповнити')
            ->assertSee('Сторінки без змісту');
    }

    public function test_checklist_requires_login(): void
    {
        $this->get('/admin/content-checklist')->assertRedirect();
    }
}
