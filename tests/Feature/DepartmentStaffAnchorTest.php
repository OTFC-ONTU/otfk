<?php

namespace Tests\Feature;

use App\Models\Department;
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
}
