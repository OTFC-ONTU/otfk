<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\News;
use App\Models\Page;
use App\Models\Specialty;
use App\Models\User;
use App\Support\AdminPreview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Tests\TestCase;

/**
 * Превʼю несохранённой форми (кнопка «Превʼю» у формах сторінок/новин):
 * слепок стану форми в кеші → /admin-preview/{token} рендерить публічний
 * шаблон без запису в БД. Лише для залогінених; чужий/протухлий токен — 404.
 */
class FormPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_gets_404_even_with_valid_token(): void
    {
        $token = AdminPreview::store('page', new Page, ['title' => 'Секрет']);

        $this->get('/admin-preview/' . $token)->assertNotFound();
    }

    public function test_unknown_token_returns_404(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin-preview/nemaye-takogo-tokena')
            ->assertNotFound();
    }

    public function test_new_page_form_state_renders_without_saving(): void
    {
        // Контент-міграції самі наповнюють таблицю — фіксуємо, що превʼю нічого не додає.
        $before = Page::count();

        $token = AdminPreview::store('page', new Page, [
            'title' => 'Ще не збережена сторінка',
            'body' => '<p>Текст з форми.</p>',
            'is_published' => false,
        ]);

        $this->actingAs(User::factory()->create())
            ->get('/admin-preview/' . $token)
            ->assertOk()
            ->assertSee('Ще не збережена сторінка')
            ->assertSee('Текст з форми.')
            ->assertSee('Попередній перегляд');

        $this->assertSame($before, Page::count());
    }

    public function test_edited_record_preview_shows_unsaved_changes(): void
    {
        $page = Page::create([
            'title' => 'Стара назва',
            'slug' => 'stara-nazva',
            'body' => '<p>Старий текст.</p>',
            'is_published' => true,
        ]);

        $token = AdminPreview::store('page', $page, ['title' => 'Нова назва з форми']);

        $this->actingAs(User::factory()->create())
            ->get('/admin-preview/' . $token)
            ->assertOk()
            ->assertSee('Нова назва з форми')
            ->assertSee('Старий текст.');

        $this->assertSame('Стара назва', $page->fresh()->title);
    }

    public function test_news_form_state_renders_without_saving(): void
    {
        $before = News::count();

        $token = AdminPreview::store('news', new News, [
            'title' => 'Ще не збережена новина',
            'body' => '<p>Тіло новини з форми.</p>',
            'published_at' => now()->toDateTimeString(),
            'is_published' => false,
        ]);

        $this->actingAs(User::factory()->create())
            ->get('/admin-preview/' . $token)
            ->assertOk()
            ->assertSee('Ще не збережена новина')
            ->assertSee('Тіло новини з форми.')
            ->assertSee('Попередній перегляд');

        $this->assertSame($before, News::count());
    }

    public function test_specialty_form_state_renders_without_saving(): void
    {
        $before = Specialty::count();

        $token = AdminPreview::store('specialty', new Specialty, [
            'title' => 'Ще не збережена спеціальність',
            'code' => 'F2',
            'short_description' => 'Опис з форми.',
            'is_published' => false,
        ]);

        $this->actingAs(User::factory()->create())
            ->get('/admin-preview/' . $token)
            ->assertOk()
            ->assertSee('Ще не збережена спеціальність')
            ->assertSee('Опис з форми.')
            ->assertSee('Попередній перегляд');

        $this->assertSame($before, Specialty::count());
    }

    public function test_department_preview_shows_unsaved_changes(): void
    {
        $department = Department::create([
            'title' => 'Стара назва підрозділу',
            'slug' => 'stara-nazva-pidrozdilu',
            'type' => 'kafedra',
            'is_published' => true,
        ]);

        $token = AdminPreview::store('department', $department, ['title' => 'Нова назва підрозділу']);

        $this->actingAs(User::factory()->create())
            ->get('/admin-preview/' . $token)
            ->assertOk()
            ->assertSee('Нова назва підрозділу')
            ->assertSee('Попередній перегляд');

        $this->assertSame('Стара назва підрозділу', $department->fresh()->title);
    }

    public function test_unknown_array_state_keeps_saved_value(): void
    {
        $page = Page::create([
            'title' => 'Сторінка',
            'slug' => 'storinka-z-oblozhkoyu',
            'cover_image' => 'pages/zberezhena.jpg',
            'is_published' => true,
        ]);

        $token = AdminPreview::store('page', $page, [
            'title' => 'Сторінка з файлом',
            'cover_image' => [['not' => 'a-file-upload-state']],
        ]);

        $this->assertSame('pages/zberezhena.jpg', AdminPreview::get($token)['attributes']['cover_image']);

        $this->actingAs(User::factory()->create())
            ->get('/admin-preview/' . $token)
            ->assertOk()
            ->assertSee('Сторінка з файлом');
    }

    public function test_saved_file_path_in_upload_state_passes_through(): void
    {
        $token = AdminPreview::store('page', new Page, [
            'title' => 'Сторінка',
            'cover_image' => ['uuid' => 'pages/zberezhena.jpg'],
        ]);

        $this->assertSame('pages/zberezhena.jpg', AdminPreview::get($token)['attributes']['cover_image']);
    }

    public function test_removed_cover_previews_without_cover(): void
    {
        $page = Page::create([
            'title' => 'Сторінка',
            'slug' => 'storinka-bez-oblozhky',
            'cover_image' => 'pages/zberezhena.jpg',
            'is_published' => true,
        ]);

        $token = AdminPreview::store('page', $page, ['cover_image' => []]);

        $this->assertNull(AdminPreview::get($token)['attributes']['cover_image']);
    }

    public function test_fresh_upload_is_copied_to_public_disk_and_shown(): void
    {
        Storage::fake('public');

        // Протухла копія від попереднього превʼю — має прибратися при новому слепку.
        Storage::disk('public')->put('admin-preview/stara-kopiya.jpg', 'old');
        touch(Storage::disk('public')->path('admin-preview/stara-kopiya.jpg'), now()->subHour()->getTimestamp());

        $tmpName = 'abc123-meta' . base64_encode('oblozhka.jpg') . '-.jpg';
        Storage::disk('local')->put('livewire-tmp/' . $tmpName, 'fake-image-bytes');
        $upload = new TemporaryUploadedFile($tmpName, 'local');

        $token = AdminPreview::store('page', new Page, [
            'title' => 'Сторінка зі свіжою обкладинкою',
            'cover_image' => ['uuid' => $upload],
        ]);

        $copied = 'admin-preview/' . $token . '-cover_image.jpg';
        Storage::disk('public')->assertExists($copied);
        Storage::disk('public')->assertMissing('admin-preview/stara-kopiya.jpg');
        $this->assertSame($copied, AdminPreview::get($token)['attributes']['cover_image']);

        $this->actingAs(User::factory()->create())
            ->get('/admin-preview/' . $token)
            ->assertOk()
            ->assertSee('storage/' . $copied, false);

        Storage::disk('local')->delete('livewire-tmp/' . $tmpName);
    }
}
