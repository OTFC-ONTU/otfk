<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Персональні сторінки працівників (/personal/{slug}) та клікабельність карток
 * у складі підрозділу / на сторінці адміністрації.
 */
class StaffProfileTest extends TestCase
{
    use RefreshDatabase;

    private function makeTeacher(array $attributes = []): Staff
    {
        $department = Department::create([
            'title' => 'Комісія тестових дисциплін',
            'type' => 'tsyklova-komisiya',
            'is_published' => true,
        ]);

        return Staff::create(array_merge([
            'full_name' => 'Тестенко Тест Тестович',
            'position' => 'викладач',
            'category' => 'teacher',
            'department_id' => $department->id,
            'bio' => '<p>Тестенко Тест Тестович <strong>Посада:</strong> викладач <strong>Стаж роботи '
                . '(педагогічний):</strong> 5 років      Результати професійної та наукової діяльності викладача - '
                . '<a href="/prof-testenko">посилання</a>.</p>',
            'is_published' => true,
        ], $attributes));
    }

    public function test_slug_is_generated_from_full_name(): void
    {
        $this->assertSame('testenko-test-testovic', $this->makeTeacher()->slug);
    }

    public function test_slug_stays_unique_for_namesakes(): void
    {
        $first = $this->makeTeacher();
        $second = Staff::create(['full_name' => $first->full_name, 'category' => 'teacher']);

        $this->assertSame($first->slug . '-2', $second->slug);
    }

    public function test_profile_page_shows_facts_and_links_from_bio(): void
    {
        $teacher = $this->makeTeacher();

        $this->get('/personal/' . $teacher->slug)
            ->assertOk()
            ->assertSee('Тестенко Тест Тестович')
            ->assertSee('Стаж роботи (педагогічний)')
            ->assertSee('5 років')
            ->assertSee('Результати професійної та наукової діяльності викладача')
            ->assertSee('/prof-testenko');
    }

    public function test_unpublished_profile_returns_404(): void
    {
        $teacher = $this->makeTeacher(['is_published' => false]);

        $this->get('/personal/' . $teacher->slug)->assertNotFound();
    }

    public function test_department_and_administration_cards_link_to_profiles(): void
    {
        $teacher = $this->makeTeacher();
        $head = Staff::create([
            'full_name' => 'Директоренко Директор Директорович',
            'category' => 'administration',
            'is_published' => true,
        ]);

        $this->get('/struktura/' . $teacher->department->slug)
            ->assertOk()
            ->assertSee('href="' . route('staff.show', $teacher) . '"', false);

        $this->get('/administratsiya')
            ->assertOk()
            ->assertSee('href="' . route('staff.show', $head) . '"', false);
    }

    public function test_sitemap_lists_staff_profiles(): void
    {
        $teacher = $this->makeTeacher();

        $this->get('/sitemap.xml')->assertOk()->assertSee(route('staff.show', $teacher), false);
    }
}
