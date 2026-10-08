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
use App\Support\LocalizedUrl;
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

    public function test_teacher_card_is_one_link_to_staff_page(): void
    {
        $department = Department::query()->first();
        $department->update(['is_published' => true]);
        $profile = Page::create(['title' => 'Результати професійної діяльності викладача', 'slug' => 'prof-test', 'is_published' => true]);
        $qualification = Page::create(['title' => 'Відомості про підвищення кваліфікації викладача', 'slug' => 'kval-test', 'is_published' => true]);
        $staff = Staff::create([
            'full_name' => 'Тестова Олена Петрівна', 'position' => 'викладач', 'category' => 'teacher',
            'department_id' => $department->id, 'is_published' => true,
            'profile_page_id' => $profile->id, 'qualification_page_id' => $qualification->id,
        ]);
        $staffUrl = LocalizedUrl::route('staff.show', $staff);

        $html = $this->get('/struktura/'.$department->slug)->assertOk()->getContent();
        $this->assertMatchesRegularExpression('~<a href="'.preg_quote($staffUrl, '~').'"\s+class="card card-interactive~', $html);
        $this->assertStringNotContainsString('href="'.url('/prof-test').'"', $html);
        $this->assertStringNotContainsString('public.staff_', $html);

        $this->get($staffUrl)->assertOk();
    }

    public function test_specialty_cards_link_programs_to_anchors_on_specialty_page(): void
    {
        $specialty = Specialty::query()->where('is_published', true)->first();
        $specialty->update(['description' => '<h2>ОПП «Тестова освітня програма»</h2><p>Мета програми.</p>']);
        $program = Program::create([
            'specialty_id' => $specialty->id, 'title' => 'Тестова освітня програма',
            'external_url' => 'https://example.org/opp-test.pdf', 'sort_order' => 99,
        ]);
        $orphan = Program::create([
            'specialty_id' => $specialty->id, 'title' => 'Програма без розділу',
            'external_url' => 'https://example.org/opp-orphan.pdf', 'sort_order' => 100,
        ]);
        $showUrl = LocalizedUrl::route('specialties.show', $specialty);

        // У картці списку — посилання на якорі сторінки спеціальності, без файлів
        $html = $this->get('/spetsialnosti')->assertOk()
            ->assertSee('Тестова освітня програма')
            ->assertSee('href="'.$showUrl.'#opp-testova-osvitnia-programa"', false)
            ->assertSee('href="'.$showUrl.'#opp-programa-bez-rozdilu"', false)
            ->assertDontSee('href="https://example.org/opp-test.pdf"', false)
            ->assertSee(__('public.programs'))
            ->getContent();

        // Картка — не обгортка-посилання: посилання ОПП не вкладені в інше посилання.
        $this->assertStringContainsString('<article class="card card-interactive group relative', $html);

        // Якір — на заголовку опису; програма без заголовка — на картці файлу в списку ОПП
        $page = $this->get($showUrl)->assertOk()->getContent();
        $this->assertStringContainsString('<h2 id="opp-testova-osvitnia-programa">ОПП «Тестова освітня програма»</h2>', $page);
        $this->assertSame(1, substr_count($page, 'id="opp-testova-osvitnia-programa"'));
        $this->assertStringNotContainsString(':code', $page);
        $this->assertMatchesRegularExpression('~<li\s+id="opp-programa-bez-rozdilu"[^>]*>\s*<div class="file-card-container">~', $page);
        $this->assertStringContainsString('href="https://example.org/opp-test.pdf"', $page);
        $this->assertSame('<h2>ОПП «Тестова освітня програма»</h2><p>Мета програми.</p>', $specialty->fresh()->description);
        $this->assertNotNull($orphan->id);
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

    public function test_document_category_with_section_page_shows_page_content(): void
    {
        $category = DocumentCategory::create(['title' => 'Моніторинг показників якості освіти', 'slug' => 'monitorynh-test']);
        $this->get('/dokumenty/monitorynh-test')->assertOk()->assertSee(__('public.no_documents'));

        $page = Page::create([
            'title' => 'Моніторинг показників якості освіти', 'slug' => 'monitorynh-page-test', 'is_published' => true,
            'body' => '<p><strong>РЕЗУЛЬТАТИ УСПІШНОСТІ ЗДОБУВАЧІВ ОСВІТИ ЗА 2024-2025 н.р.</strong></p>'
                .'<p><img src="/storage/mirror/otfk.od.ua/public_information/monitoring/img/1.jpg" alt="" /></p>'
                .'<p><a href="/storage/mirror/otfk.od.ua/public_information/monitoring/files/report.pdf">Звіт моніторингу</a></p>',
        ]);
        $category->update(['page_id' => $page->id]);

        $html = $this->get('/dokumenty/monitorynh-test')->assertOk()
            ->assertSee('РЕЗУЛЬТАТИ УСПІШНОСТІ ЗДОБУВАЧІВ ОСВІТИ ЗА 2024-2025 н.р.')
            ->assertSee('/storage/mirror/otfk.od.ua/public_information/monitoring/img/1.jpg', false)
            ->assertDontSee(__('public.no_documents'))
            ->getContent();
        $this->assertStringContainsString('file-card', $html);

        $page->update(['is_published' => false]);
        $this->get('/dokumenty/monitorynh-test')->assertOk()->assertSee(__('public.no_documents'));
    }

    public function test_file_cards_keep_manual_numbering_of_the_original(): void
    {
        $html = FileCards::render('<p>1) <a href="/storage/polozhennya.pdf">Положення про коледж</a></p><p>93. <a href="/storage/nakaz.pdf">Наказ</a></p>');

        $this->assertStringContainsString('1) Положення про коледж', $html);
        $this->assertStringContainsString('93. Наказ', $html);
    }

    public function test_section_page_own_url_redirects_to_its_document_category(): void
    {
        $page = Page::create(['title' => 'Звіти', 'slug' => 'zvity-page-test', 'is_published' => true, 'body' => '<p>Звіти коледжу</p>']);
        DocumentCategory::create(['title' => 'Звіти', 'slug' => 'zvity-test', 'page_id' => $page->id]);

        $this->get('/zvity-page-test')->assertStatus(301)->assertRedirect(url('/dokumenty/zvity-test'));
        $this->get('/en/zvity-page-test')->assertStatus(301)->assertRedirect(url('/en/dokumenty/zvity-test'));
    }
}
