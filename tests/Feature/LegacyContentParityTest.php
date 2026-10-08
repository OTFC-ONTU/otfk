<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\Page;
use App\Models\Program;
use App\Models\Setting;
use App\Models\Specialty;
use App\Models\Staff;
use App\Support\FileCards;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class LegacyContentParityTest extends TestCase
{
    use RefreshDatabase;

    public function test_contacts_page_shows_cms_page_text_instead_of_settings_block(): void
    {
        $this->get('/kontakty')->assertOk()->assertSee(__('feature.contact_the_college'));

        Page::create([
            'title' => 'Контакти', 'slug' => 'kontakty', 'is_published' => true,
            'body' => '<p>Поштова адреса коледжу для подання інформаційного запиту:</p>',
            'title_en' => 'Contacts', 'body_en' => '<p>Postal address of the college for information requests:</p>',
            'translation_published' => true,
        ]);

        $this->get('/kontakty')->assertOk()
            ->assertSee('Поштова адреса коледжу для подання інформаційного запиту:')
            ->assertDontSee(__('public.contact_intro'))
            ->assertDontSee('name="message"', false);
        $this->get('/en/kontakty')->assertOk()->assertSee('Postal address of the college for information requests:');
    }

    public function test_empty_footer_about_hides_footer_description(): void
    {
        Setting::updateOrCreate(['key' => 'footer_about'], ['value' => 'Опис у підвалі', 'type' => 'textarea']);
        Cache::flush();
        $this->get('/')->assertOk()->assertSee('Опис у підвалі');

        Setting::where('key', 'footer_about')->update(['value' => null]);
        Cache::flush();
        $this->get('/')->assertOk()
            ->assertDontSee('Опис у підвалі')
            ->assertDontSee(__('layout.about'));
    }

    public function test_document_title_longer_than_255_characters_is_stored_in_full(): void
    {
        $title = str_repeat('Результати опитування здобувачів освіти щодо якості освітньої програми ', 6);
        $category = DocumentCategory::query()->first() ?? DocumentCategory::create(['title' => 'Звіти', 'slug' => 'zvity']);

        $document = Document::create(['document_category_id' => $category->id, 'title' => $title, 'external_url' => 'https://example.org/a.pdf']);

        $this->assertGreaterThan(255, mb_strlen($title));
        $this->assertSame($title, $document->fresh()->title);
    }

    public function test_paragraph_with_only_a_file_link_renders_as_file_card(): void
    {
        Page::create([
            'title' => 'Освітньо-професійні програми', 'slug' => 'opp-test', 'is_published' => true,
            'body' => '<p><strong>Освітньо-професійні програми 2022 року</strong></p>'
                .'<p><a href="/storage/imported/files/fac_123_1.pdf">ОПП спеціальності: 123 Комп’ютерна інженерія</a></p>'
                .'<p>Див. <a href="/storage/imported/files/plan.pdf">план</a> у тексті.</p>',
        ]);

        $html = $this->get('/opp-test')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'class="file-card not-prose"'));
        $this->assertStringContainsString('ОПП спеціальності: 123 Комп’ютерна інженерія</a>', $html);
        $this->assertStringContainsString('href="/storage/imported/files/fac_123_1.pdf" target="_blank" rel="noopener" class="file-card__title"', $html);
        $this->assertStringContainsString('<p>Див. <a href="/storage/imported/files/plan.pdf">план</a> у тексті.</p>', $html);
        $this->assertStringContainsString(__('public.download'), $html);
        $this->assertMatchesRegularExpression('/class="text-slate-700"\s+aria-current="page"/', $html);
    }

    public function test_file_cards_preserve_query_and_fragment_on_all_actions(): void
    {
        $html = FileCards::render('<p><a href="/storage/plan.pdf?download=1&amp;version=2#page=4">План навчання</a></p>');

        $this->assertSame(3, substr_count($html, 'href="/storage/plan.pdf?download=1&amp;version=2#page=4"'));
        $this->assertStringContainsString('aria-label="'.__('public.download').': План навчання"', $html);
        $this->assertSame('<p>Текст <a href="/plan.pdf?version=2#page=4">плану</a></p>',
            FileCards::render('<p>Текст <a href="/plan.pdf?version=2#page=4">плану</a></p>'));
    }

    public function test_teacher_card_links_to_profile_and_qualification_pages(): void
    {
        $department = Department::query()->first();
        $department->update(['is_published' => true]);
        $profile = Page::create(['title' => 'Результати професійної діяльності викладача', 'slug' => 'prof-test', 'is_published' => true]);
        $qualification = Page::create(['title' => 'Відомості про підвищення кваліфікації викладача', 'slug' => 'kval-test', 'is_published' => true]);
        Staff::create([
            'full_name' => 'Тестова Олена Петрівна', 'position' => 'викладач', 'category' => 'teacher',
            'department_id' => $department->id, 'is_published' => true,
            'profile_page_id' => $profile->id, 'qualification_page_id' => $qualification->id,
        ]);

        $this->get('/struktura/'.$department->slug)->assertOk()
            ->assertSee('href="'.url('/prof-test').'"', false)
            ->assertSee('href="'.url('/kval-test').'"', false)
            ->assertSee(__('public.staff_qualification_page'));
        $this->get('/en/struktura/'.$department->slug)->assertOk()
            ->assertSee('href="'.url('/en/prof-test').'"', false);
    }

    public function test_specialty_cards_list_programs_with_file_links(): void
    {
        $specialty = Specialty::query()->where('is_published', true)->first();
        Program::create([
            'specialty_id' => $specialty->id, 'title' => 'Тестова освітня програма',
            'external_url' => 'https://example.org/opp-test.pdf', 'sort_order' => 99,
        ]);

        $html = $this->get('/spetsialnosti')->assertOk()
            ->assertSee('Тестова освітня програма')
            ->assertSee('href="https://example.org/opp-test.pdf"', false)
            ->assertSee(__('public.programs'))
            ->getContent();

        // Картка — не обгортка-посилання: посилання ОПП не вкладені в інше посилання.
        $this->assertStringContainsString('<article class="card card-interactive group relative', $html);
    }

    public function test_structure_commission_section_shows_intro_and_rating_files(): void
    {
        Department::create(['title' => 'Комісія тестових дисциплін', 'slug' => 'komisiia-test', 'type' => 'tsyklova-komisiya', 'is_published' => true]);
        Page::create([
            'title' => 'Циклові комісії', 'slug' => 'ciklovi-komisiyi', 'is_published' => true,
            'body' => '<p>У структурі коледжу представлені циклові комісії різних дисциплін.</p>'
                .'<ul><li><a href="/struktura/komisiia-test">Комісія тестова</a></li></ul>'
                .'<p><a href="/storage/mirror/otfk.od.ua/structure/cycles_commissions/files/28_10_2025_1.pdf">Рейтингове оцінювання викладачів за 2023-2024 н.р.</a></p>',
        ]);

        $html = $this->get('/struktura')->assertOk()
            ->assertSee('У структурі коледжу представлені циклові комісії різних дисциплін.')
            ->assertSee('Рейтингове оцінювання викладачів за 2023-2024 н.р.')
            ->assertSee('href="/storage/mirror/otfk.od.ua/structure/cycles_commissions/files/28_10_2025_1.pdf"', false)
            ->assertDontSee('Комісія тестова')
            ->getContent();

        $this->assertStringContainsString('file-card', $html);
    }
}
