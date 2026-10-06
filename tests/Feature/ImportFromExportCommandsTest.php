<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\News;
use App\Models\Page;
use App\Models\Setting;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Контракти імпорт-команд у режимі --from-export (читання ЛОКАЛЬНОГО дзеркала
 * site-audit/… замість HTTP): фікстура — синтетичний мінімальний експорт у
 * tests/fixtures/otfk-export. Ключові інваріанти: ідемпотентність повторного
 * запуску, маркери <!--imported-from:…-->, копіювання файлів у storage.
 */
class ImportFromExportCommandsTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): string
    {
        return base_path('tests/fixtures/otfk-export');
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_import_news_from_export_creates_article_with_cover_and_marker(): void
    {
        $this->artisan('otfk:import-news', ['--from-export' => $this->fixture()])->assertSuccessful();

        // Тестова БД сидиться демо-даними (TestCase::$seed) — рахуємо лише імпортоване.
        $imported = News::where('body', 'like', '%<!--imported-from:%');
        $this->assertSame(1, $imported->count());

        $news = $imported->first();
        $this->assertSame('Тестова новина про коледж', $news->title);
        $this->assertSame('2025-05-15', $news->published_at->format('Y-m-d'));
        $this->assertTrue($news->is_published);
        $this->assertNotNull($news->telegram_posted_at, 'Імпортована новина не повинна піти автопостом у Telegram');
        $this->assertStringContainsString('<!--imported-from:https://otfk.od.ua/news/Testova_novyna/-->', $news->body);

        // Перше фото стало обкладинкою та скопійоване у storage.
        $this->assertSame('news/imported/test.jpg', $news->cover_image);
        Storage::disk('public')->assertExists('news/imported/test.jpg');
    }

    public function test_import_news_from_export_is_idempotent(): void
    {
        $this->artisan('otfk:import-news', ['--from-export' => $this->fixture()])->assertSuccessful();
        $this->artisan('otfk:import-news', ['--from-export' => $this->fixture()])->assertSuccessful();

        $this->assertSame(1, News::where('body', 'like', '%<!--imported-from:%')->count());
    }

    public function test_import_docs_from_export_reads_saved_html_and_copies_pdf(): void
    {
        $category = DocumentCategory::firstOrCreate(['slug' => 'normatyvna-baza'], ['title' => 'Нормативна база']);

        $this->artisan('otfk:import-docs', ['--from-export' => $this->fixture()])->assertSuccessful();

        $doc = Document::where('document_category_id', $category->id)->where('title', 'Положення про тестовий коледж')->first();
        $this->assertNotNull($doc, 'PDF зі збереженого HTML має стати документом категорії');
        $this->assertNotNull($doc->file_path);
        Storage::disk('public')->assertExists($doc->file_path);

        // Повторний запуск не дублює документів (дедуплікація за назвою).
        $this->artisan('otfk:import-docs', ['--from-export' => $this->fixture()])->assertSuccessful();
        $this->assertSame(1, Document::where('title', 'Положення про тестовий коледж')->count());
    }

    public function test_import_pages_creates_cms_page_with_assets_and_is_idempotent(): void
    {
        $this->artisan('otfk:import-pages', ['--from-export' => $this->fixture()])->assertSuccessful();

        $page = Page::where('body', 'like', '%<!--imported-from:https://otfk.od.ua/student/test_page-->%')->first();
        $this->assertNotNull($page);
        $this->assertTrue($page->is_published);
        $this->assertSame('Тестова сторінка студенту', $page->title);
        $this->assertSame('testova-storinka-studentu', $page->slug);
        $this->assertSame('Опис тестової сторінки для фікстури.', $page->meta_description);

        // Зображення та документ скопійовано у storage, посилання переписані.
        $this->assertStringContainsString('/storage/imported/images/student/test_page/img/photo.jpg', $page->body);
        $this->assertStringContainsString('/storage/imported/files/public_information/provision/files/doc1.pdf', $page->body);
        Storage::disk('public')->assertExists('imported/images/student/test_page/img/photo.jpg');
        Storage::disk('public')->assertExists('imported/files/public_information/provision/files/doc1.pdf');

        // Сторінка комісії та склад НЕ імпортуються як CMS-сторінки (їх веде otfk:import-staff).
        $this->assertNull(Page::where('body', 'like', '%/structure/cycles_commissions/test_commission-->%')->first());

        $count = Page::count();
        $this->artisan('otfk:import-pages', ['--from-export' => $this->fixture()])->assertSuccessful();
        $this->assertSame($count, Page::count(), 'Повторний запуск не має плодити сторінки');
    }

    public function test_import_staff_creates_departments_and_people(): void
    {
        $this->artisan('otfk:import-staff', ['--from-export' => $this->fixture()])->assertSuccessful();

        // Відділення з departments.md і комісія з каталогу cycles_commissions
        // (демо-підрозділи сидера лишаються — шукаємо за назвою).
        $viddilennya = Department::where('title', 'Тестове відділення')->first();
        $this->assertNotNull($viddilennya);
        $this->assertSame('viddilennya', $viddilennya->type);

        $commission = Department::where('title', 'Тестова циклова комісія')->first();
        $this->assertNotNull($commission);
        $this->assertSame('tsyklova-komisiya', $commission->type);
        $this->assertStringContainsString('Опис діяльності тестової комісії', $commission->description);
        $this->assertStringNotContainsString('Викладацький склад', $commission->description);

        // Викладач із таблиці personel: ПІБ, посада з ролі + «Посада:», фото, ступінь.
        $teacher = Staff::where('full_name', 'Іванов Іван Іванович')->first();
        $this->assertNotNull($teacher);
        $this->assertSame('Голова тестової комісії, викладач', $teacher->position);
        $this->assertSame('кандидат технічних наук', $teacher->academic_degree);
        $this->assertSame($commission->id, $teacher->department_id);
        Storage::disk('public')->assertExists($teacher->photo);

        // Адміністрація з leaders_of_the_college.md.
        $admin = Staff::where('full_name', 'Петрова Марія Василівна')->first();
        $this->assertNotNull($admin);
        $this->assertSame('administration', $admin->category);
        $this->assertSame('Директор тестового коледжу', $admin->position);
        $this->assertSame('(048)000-11-22', $admin->phone);

        // Ідемпотентність: повторний запуск не плодить записів.
        $staff = Staff::count();
        $departments = Department::count();
        $this->artisan('otfk:import-staff', ['--from-export' => $this->fixture()])->assertSuccessful();
        $this->assertSame($staff, Staff::count());
        $this->assertSame($departments, Department::count());
    }

    public function test_import_staff_replace_demo_removes_only_seeder_fakes(): void
    {
        // Демо-персонал і демо-підрозділи вже насиджені SiteSeeder-ом ($seed у TestCase).
        Staff::firstOrCreate(['full_name' => 'Реальна Людина Тестівна'], ['position' => 'викладач', 'category' => 'teacher', 'is_published' => true]);
        $this->assertNotNull(Staff::where('full_name', 'Петренко Олександр Іванович')->first());
        $this->assertNotNull(Department::where('slug', 'kafedra-ipz')->first());

        $this->artisan('otfk:import-staff', ['--from-export' => $this->fixture(), '--replace-demo' => true])->assertSuccessful();

        $this->assertNull(Staff::where('full_name', 'Петренко Олександр Іванович')->first());
        $this->assertNotNull(Staff::where('full_name', 'Реальна Людина Тестівна')->first());
        $this->assertNull(Department::where('slug', 'kafedra-ipz')->first());
    }

    public function test_import_contacts_writes_settings_and_resets_cache(): void
    {
        Setting::updateOrCreate(['key' => 'contact_email'], ['value' => 'info@otfk.od.ua', 'group' => 'contacts', 'type' => 'text']);
        $this->assertSame('info@otfk.od.ua', Setting::get('contact_email')); // прогріваємо кеш

        $this->artisan('otfk:import-contacts', ['--from-export' => $this->fixture()])->assertSuccessful();

        $this->assertSame('test@example.com', Setting::get('contact_email'), 'Кеш settings.map має бути скинутий');
        $this->assertSame('test@example.com', Setting::get('feedback_email'));
        $this->assertSame('(048) 111-22-33', Setting::get('contact_phone'));
        $this->assertSame('м.Одеса, вул. Тестова, 1, 65000', Setting::get('contact_address'));
        $this->assertSame('https://www.google.com/maps/embed?pb=test123', Setting::get('map_embed'));
    }

    public function test_commands_fail_gracefully_without_valid_export_dir(): void
    {
        $this->artisan('otfk:import-pages', ['--from-export' => '/no/such/dir'])->assertFailed();
        $this->artisan('otfk:import-staff', ['--from-export' => '/no/such/dir'])->assertFailed();
        $this->artisan('otfk:import-contacts', ['--from-export' => '/no/such/dir'])->assertFailed();
    }
}
