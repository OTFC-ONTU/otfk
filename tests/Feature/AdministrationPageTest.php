<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Setting;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Редизайн /administratsiya: світла шапка з лічильником, директор окремим
 * блоком, групи керівництва, клікабельні телефони, контакти приймальні та CTA.
 */
class AdministrationPageTest extends TestCase
{
    use RefreshDatabase;

    private function makeLeader(string $name, string $position, array $attributes = []): Staff
    {
        return Staff::create(array_merge([
            'full_name' => $name,
            'position' => $position,
            'category' => 'administration',
            'is_published' => true,
        ], $attributes));
    }

    private function makeTeam(): void
    {
        $this->makeLeader('Іваненко Ірина Іванівна', 'Директор коледжу', [
            'sort_order' => 0,
            'phone' => '(048) 753-16-51',
            'academic_degree' => 'кандидат технічних наук',
            'bio' => '<p>Директор коледжу з 2020 року.</p>',
        ]);
        $this->makeLeader('Петренко Петро Петрович', 'Заступник директора з навчальної роботи', [
            'sort_order' => 1,
            'phone' => '(048) 753-16-55',
        ]);
        $this->makeLeader('Сидоренко Світлана Сергіївна', 'Завідуюча технологічним відділенням', [
            'sort_order' => 2,
            'phone' => '(048) 753-16-78',
        ]);
    }

    public function test_page_has_light_header_with_leader_counter(): void
    {
        $this->makeTeam();

        $this->get('/administratsiya')
            ->assertOk()
            // Світла шапка розділу — як на новинах, структурі та спеціальностях
            ->assertSee('border-b border-slate-200/70 bg-slate-50/80', false)
            ->assertDontSee('<section class="bg-brand-950">', false)
            ->assertSee('Адміністрація коледжу')
            ->assertSee('3 керівники');
    }

    public function test_director_is_shown_as_separate_block_with_profile_and_bio(): void
    {
        $this->makeTeam();
        $head = Staff::where('full_name', 'Іваненко Ірина Іванівна')->firstOrFail();

        $this->get('/administratsiya')
            ->assertOk()
            ->assertSee('Керівник коледжу')
            ->assertSee('Профіль керівника')
            ->assertSee('href="' . route('staff.show', $head) . '"', false)
            ->assertSee('Директор коледжу з 2020 року.');

        // Директора не дублюємо в групах керівництва — на сторінці він один раз
        $this->assertSame(1, substr_count($this->get('/administratsiya')->getContent(), $head->full_name));
    }

    public function test_leaders_are_grouped_by_role_from_position(): void
    {
        $this->makeTeam();

        $this->get('/administratsiya')
            ->assertOk()
            ->assertSee('Заступники директора')
            ->assertSee('Керівники відділень та служб')
            ->assertSeeInOrder(['Заступники директора', 'Керівники відділень та служб'], false);
    }

    public function test_phones_are_clickable(): void
    {
        $this->makeTeam();

        $this->get('/administratsiya')
            ->assertOk()
            ->assertSee('href="tel:0487531651"', false)
            ->assertSee('href="tel:0487531655"', false);
    }

    public function test_card_links_to_department_when_it_is_set(): void
    {
        $department = Department::create([
            'title' => 'Комісія тестових дисциплін',
            'type' => 'tsyklova-komisiya',
            'is_published' => true,
        ]);

        $this->makeLeader('Тестенко Тест Тестович', 'Завідувач відділенням', [
            'department_id' => $department->id,
        ]);

        $this->get('/administratsiya')
            ->assertOk()
            ->assertSee('href="' . route('structure.show', $department) . '"', false)
            ->assertSee('Комісія тестових дисциплін');
    }

    public function test_reception_contacts_come_from_settings(): void
    {
        $this->makeTeam();

        Setting::updateOrCreate(['key' => 'contact_address'], ['value' => 'м. Одеса, вул. Балківська, 54']);
        Setting::updateOrCreate(['key' => 'contact_email'], ['value' => 'otkua@ukr.net']);
        Setting::updateOrCreate(['key' => 'work_hours'], ['value' => 'Пн–Пт: 08:00–17:00']);
        // кеш settings.map скидається обсервером у Setting::booted()

        $this->get('/administratsiya')
            ->assertOk()
            ->assertSee('Контакти приймальні')
            ->assertSee('м. Одеса, вул. Балківська, 54')
            ->assertSee('href="mailto:otkua@ukr.net"', false)
            ->assertSee('Пн–Пт: 08:00–17:00');
    }

    public function test_reception_contacts_block_is_hidden_without_settings(): void
    {
        $this->makeTeam();

        foreach (['contact_address', 'contact_phone', 'contact_email', 'work_hours'] as $key) {
            Setting::updateOrCreate(['key' => $key], ['value' => '']);
        }
        // кеш settings.map скидається обсервером у Setting::booted()

        $this->get('/administratsiya')
            ->assertOk()
            ->assertDontSee('Контакти приймальні');
    }

    public function test_final_call_to_action_links_to_contacts(): void
    {
        $this->makeTeam();

        $this->get('/administratsiya')
            ->assertOk()
            ->assertSee('Потрібна консультація щодо вступу?')
            ->assertSee('href="' . route('contacts') . '"', false);
    }

    public function test_empty_state_is_shown_without_staff(): void
    {
        Staff::query()->delete();

        $this->get('/administratsiya')
            ->assertOk()
            ->assertSee('Інформацію про адміністрацію незабаром буде додано.');
    }
}
