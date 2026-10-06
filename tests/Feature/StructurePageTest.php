<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Сторінка «Структура коледжу» (/struktura): світла шапка з лічильниками,
 * навігація-якорі по групах підрозділів та картки підрозділів.
 */
class StructurePageTest extends TestCase
{
    use RefreshDatabase;

    private function makeDepartment(array $attributes = []): Department
    {
        return Department::create(array_merge([
            'title' => 'Відділення тестових систем',
            'type' => 'viddilennya',
            'description' => '<p>Відділення готує фахівців з тестування програмного забезпечення.</p>',
            'is_published' => true,
        ], $attributes));
    }

    /** Демо-підрозділи з сидера заважають перевіряти лічильники — прибираємо їх. */
    private function clearStructure(): void
    {
        Staff::query()->update(['department_id' => null]);
        Department::query()->delete();
    }

    public function test_page_shows_group_headings_and_department_cards(): void
    {
        $department = $this->makeDepartment();
        $commission = $this->makeDepartment([
            'title' => 'Комісія тестових дисциплін',
            'type' => 'tsyklova-komisiya',
        ]);

        $this->get('/struktura')
            ->assertOk()
            ->assertSee('Структура коледжу')
            ->assertSee('Відділення')
            ->assertSee('Циклова комісія')
            ->assertSee($department->title)
            ->assertSee($commission->title)
            ->assertSee('href="' . route('structure.show', $department) . '"', false)
            ->assertSee('href="' . route('structure.show', $commission) . '"', false);
    }

    public function test_header_counts_units_and_staff_with_ukrainian_declension(): void
    {
        $this->clearStructure();

        $department = $this->makeDepartment();
        foreach (['Перший Тест Тестович', 'Другий Тест Тестович'] as $name) {
            Staff::create([
                'full_name' => $name,
                'category' => 'teacher',
                'department_id' => $department->id,
                'is_published' => true,
            ]);
        }

        $this->get('/struktura')
            ->assertOk()
            ->assertSee('1 підрозділ')
            ->assertSee('2 співробітники');
    }

    public function test_group_anchors_are_linked_from_navigation(): void
    {
        $this->makeDepartment();

        $this->get('/struktura')
            ->assertOk()
            ->assertSee('href="#viddilennya"', false)
            ->assertSee('id="viddilennya"', false);
    }

    public function test_card_shows_plain_text_excerpt_of_description(): void
    {
        $this->makeDepartment();

        $this->get('/struktura')
            ->assertOk()
            ->assertSee('Відділення готує фахівців з тестування програмного забезпечення.')
            ->assertDontSee('<p>Відділення готує', false);
    }

    public function test_unpublished_departments_are_hidden(): void
    {
        $this->makeDepartment(['title' => 'Прихований підрозділ', 'is_published' => false]);

        $this->get('/struktura')
            ->assertOk()
            ->assertDontSee('Прихований підрозділ');
    }

    public function test_empty_structure_shows_placeholder_without_navigation(): void
    {
        $this->clearStructure();

        $this->get('/struktura')
            ->assertOk()
            ->assertSee('Інформацію про структурні підрозділи незабаром буде додано.')
            ->assertDontSee('href="#viddilennya"', false);
    }

    public function test_final_call_to_action_links_to_administration_and_contacts(): void
    {
        $this->makeDepartment();

        $this->get('/struktura')
            ->assertOk()
            ->assertSee('href="' . route('staff.administration') . '"', false)
            ->assertSee('href="' . route('contacts') . '"', false);
    }
    public function test_department_page_shows_summary_sidebar_and_quick_links(): void
    {
        $department = $this->makeDepartment();
        Staff::create([
            'full_name' => 'Тестенко Тест Тестович',
            'category' => 'teacher',
            'department_id' => $department->id,
            'is_published' => true,
        ]);

        $this->get('/struktura/' . $department->slug)
            ->assertOk()
            ->assertSee('Коротко про підрозділ')
            ->assertSee('Відділення')
            ->assertSee('1 співробітник')
            ->assertSee('Швидкі посилання')
            ->assertSee('href="' . route('specialties.index') . '"', false)
            ->assertSee('href="' . route('staff.administration') . '"', false);
    }

    public function test_department_page_lists_sibling_departments_of_the_same_group(): void
    {
        $department = $this->makeDepartment();
        $sibling = $this->makeDepartment(['title' => 'Відділення сусіднє']);
        $otherGroup = $this->makeDepartment([
            'title' => 'Кафедра стороння',
            'type' => 'kafedra',
        ]);

        $this->get('/struktura/' . $department->slug)
            ->assertOk()
            ->assertSee('Інші підрозділи')
            ->assertSee($sibling->title)
            ->assertDontSee($otherGroup->title);
    }

    public function test_department_description_is_rendered_in_readable_prose_block(): void
    {
        $department = $this->makeDepartment();

        $this->get('/struktura/' . $department->slug)
            ->assertOk()
            ->assertSee('prose-site', false)
            ->assertSee('Відділення готує фахівців з тестування програмного забезпечення.');
    }
}
