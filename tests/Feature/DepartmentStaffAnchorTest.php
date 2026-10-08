<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\LegacyRedirect;
use App\Models\Page;
use App\Models\Staff;
use App\Support\DepartmentStaffAnchor;
use App\Support\HtmlSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Посилання «Викладацький склад комісії» в описі підрозділу ведуть на блок карток викладачів
 * тієї ж сторінки (#vykladachi), якщо картки є; HTML у БД не змінюється.
 */
class DepartmentStaffAnchorTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_staff_page_links_point_to_staff_block(): void
    {
        $department = Department::create([
            'title' => 'Комісія тестова', 'slug' => 'komisiia-testova', 'type' => 'tsyklova-komisiya', 'is_published' => true,
            'description' => '<p><a href="/vikladaci-komisiyi-testovoyi">Викладацький склад комісії</a></p><p><a href="#vykladachi">Ручне посилання</a> і <a href="/rozklad-dzvinkiv">розклад</a></p>',
        ]);
        $stored = $department->fresh()->description;
        Staff::create(['full_name' => 'Іваненко Іван Іванович', 'department_id' => $department->id, 'category' => 'teacher', 'is_published' => true]);

        $this->get('/struktura/komisiia-testova')
            ->assertOk()
            ->assertSee('id="vykladachi"', false)
            ->assertSee('<a href="#vykladachi">Викладацький склад комісії</a>', false)
            ->assertSee('<a href="#vykladachi">Ручне посилання</a>', false)
            ->assertDontSee('vikladaci-komisiyi-testovoyi', false)
            ->assertSee('/rozklad-dzvinkiv', false);

        $this->assertSame($stored, $department->fresh()->description);
        $this->assertStringContainsString('href="#vykladachi"', HtmlSanitizer::clean('<a href="#vykladachi">x</a>'));
    }

    public function test_links_stay_when_department_has_no_staff_cards(): void
    {
        Department::create([
            'title' => 'Без карток', 'slug' => 'bez-kartok', 'type' => 'tsyklova-komisiya', 'is_published' => true,
            'description' => '<p><a href="/vikladaci-komisiyi-bez-kartok">Викладацький склад комісії</a></p>',
        ]);

        $this->get('/struktura/bez-kartok')->assertOk()->assertSee('vikladaci-komisiyi-bez-kartok', false)->assertDontSee('id="vykladachi"', false);
        $this->assertSame('<a class="x" href="#vykladachi">', DepartmentStaffAnchor::links('<a class="x" href="https://otfk.od.ua/en/vikladaci-komisiyi-a?x=1">'));
    }

    public function test_old_staff_list_pages_redirect_to_department_block_and_leave_sitemap(): void
    {
        $department = Department::create([
            'title' => 'Комісія тестова', 'slug' => 'komisiia-testova', 'type' => 'tsyklova-komisiya', 'is_published' => true,
            'description' => '<p><a href="/vikladaci-komisiyi-testovoyi">Викладацький склад комісії</a></p>',
        ]);
        Staff::create(['full_name' => 'Іваненко Іван', 'slug' => 'ivanenko-ivan', 'department_id' => $department->id, 'category' => 'teacher', 'is_published' => true]);
        $merged = Department::create(['title' => 'Економіки та товарознавства', 'slug' => 'komisiia-ekonomiki-ta-tovaroznavstva', 'type' => 'tsyklova-komisiya', 'is_published' => true]);
        Staff::create(['full_name' => 'Петренко Петро', 'slug' => 'petrenko-petro', 'department_id' => $merged->id, 'category' => 'teacher', 'is_published' => true]);
        foreach (['vikladaci-komisiyi-testovoyi', 'vikladaci-komisiyi-tovaroznavstva', 'vikladaci-komisiyi-bez-komisiyi'] as $slug) {
            Page::create(['title' => 'Викладачі '.$slug, 'slug' => $slug, 'body' => '<p>Біографії</p>', 'is_published' => true]);
        }

        $this->get('/vikladaci-komisiyi-testovoyi')->assertStatus(301)->assertRedirect('http://localhost/struktura/komisiia-testova#vykladachi');
        $this->get('/en/vikladaci-komisiyi-testovoyi')->assertStatus(301)->assertRedirect('http://localhost/en/struktura/komisiia-testova#vykladachi');
        $this->get('/vikladaci-komisiyi-tovaroznavstva')->assertRedirect('http://localhost/struktura/komisiia-ekonomiki-ta-tovaroznavstva#vykladachi');
        $this->get('/vikladaci-komisiyi-bez-komisiyi')->assertOk();

        $sitemap = $this->get('/sitemap.xml')->assertOk()->getContent();
        $this->assertStringNotContainsString('/vikladaci-komisiyi-testovoyi', $sitemap);
        $this->assertStringNotContainsString('/vikladaci-komisiyi-tovaroznavstva', $sitemap);
        $this->assertStringContainsString('/vikladaci-komisiyi-bez-komisiyi', $sitemap);
    }

    public function test_migration_points_legacy_redirects_straight_to_department_block(): void
    {
        $department = Department::create([
            'title' => 'Комісія тестова', 'slug' => 'komisiia-testova', 'type' => 'tsyklova-komisiya', 'is_published' => true,
            'description' => '<p><a href="/vikladaci-komisiyi-testovoyi">Викладацький склад комісії</a></p>',
        ]);
        Staff::create(['full_name' => 'Іваненко Іван', 'slug' => 'ivanenko-ivan', 'department_id' => $department->id, 'category' => 'teacher', 'is_published' => true]);
        Page::create(['title' => 'Викладачі', 'slug' => 'vikladaci-komisiyi-testovoyi', 'body' => '<p>Біографії</p>', 'is_published' => true]);
        $redirect = LegacyRedirect::create(['source_path' => '/structure/cycles_commissions/test/personel', 'target_url' => '/vikladaci-komisiyi-testovoyi', 'status_code' => 301]);

        (require database_path('migrations/2026_10_09_150000_point_legacy_staff_pages_to_department_block.php'))->up();

        $redirect->refresh();
        $this->assertSame('/struktura/komisiia-testova#vykladachi', $redirect->target_url);
        $this->assertStringContainsString('було: /vikladaci-komisiyi-testovoyi', (string) $redirect->note);
        $this->get('/structure/cycles_commissions/test/personel')->assertStatus(301)
            ->assertRedirect('http://localhost/struktura/komisiia-testova#vykladachi');
    }
}
