<?php

namespace Tests\Feature;

use App\Filament\Forms\Components\RichEditor\EmbedPlugin;
use App\Filament\Forms\Components\RichEditor\InsertImagesAction;
use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Models\Page;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Кнопка «Вставити фото» редактора: кілька фото за раз (стандартна дія Filament приймала
 * один файл, і FilePond мовчки відкидав перетягування кількох), кожне — окремим абзацом;
 * заміна виділеного фото — одним файлом. PDF — кнопкою «PDF-файл / відео», не тут.
 */
class EditorInsertImagesTest extends TestCase
{
    use RefreshDatabase;

    private function page(): Page
    {
        $this->actingAs(User::firstOrFail());

        return Page::create(['title' => 'Фото', 'slug' => 'foto-test', 'body' => '<p>Текст</p>', 'is_published' => true]);
    }

    private function action(array $arguments = []): TestAction
    {
        return TestAction::make(InsertImagesAction::NAME)
            ->schemaComponent('body', schema: 'form')
            ->arguments(['editorSelection' => ['type' => 'text', 'anchor' => 1, 'head' => 1], ...$arguments]);
    }

    public function test_toolbar_names_photo_button_and_pdf_button_clearly(): void
    {
        $page = $this->page();

        $this->get(\App\Filament\Resources\PageResource::getUrl('edit', ['record' => $page]))
            ->assertOk()
            ->assertSee(InsertImagesAction::LABEL)
            ->assertSee(EmbedPlugin::LABEL.' (на сайті — картка файлу або плеєр)')
            ->assertDontSee('Прикріпити файли');
        $this->assertStringContainsString('«'.EmbedPlugin::LABEL.'»', InsertImagesAction::HINT);
    }

    public function test_several_photos_are_inserted_in_order_as_separate_paragraphs(): void
    {
        Storage::fake('public');
        $page = $this->page();

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->callAction($this->action(), data: [
                'file' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.png'), UploadedFile::fake()->image('c.webp')],
                'alt' => 'Екскурсія',
            ])
            ->assertHasNoFormErrors()
            ->assertDispatched('run-rich-editor-commands', function (string $event, array $params): bool {
                $command = $params['commands'][0] ?? [];
                $content = $command['arguments'][0] ?? [];

                return ($command['name'] ?? null) === 'insertContent'
                    && count($content) === 3
                    && collect($content)->every(fn (array $block): bool => $block['type'] === 'paragraph'
                        && $block['content'][0]['type'] === 'image'
                        && $block['content'][0]['attrs']['alt'] === 'Екскурсія'
                        && filled($block['content'][0]['attrs']['id'])
                        && filled($block['content'][0]['attrs']['src']));
            });
    }

    /** Документ, який надішле TipTap після вставки кількох фото, зберігається з постійними адресами всіх файлів. */
    public function test_several_inserted_photos_are_moved_to_storage_on_save(): void
    {
        Storage::fake('public');
        $page = $this->page();
        $content = [];

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->callAction($this->action(), data: ['file' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.png')]])
            ->assertHasNoFormErrors()
            ->assertDispatched('run-rich-editor-commands', function (string $event, array $params) use (&$content): bool {
                $content = $params['commands'][0]['arguments'][0];

                return true;
            })
            ->set('data.body', ['type' => 'doc', 'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Текст']]],
                ...$content,
            ]])
            ->call('save')
            ->assertHasNoFormErrors();

        $body = $page->fresh()->body;
        preg_match_all('~<img[^>]*src="/storage/([^"]+)"~', $body, $sources);

        $this->assertCount(2, $sources[1], $body);
        $this->assertStringNotContainsString('livewire', $body);
        foreach ($sources[1] as $path) {
            Storage::disk('public')->assertExists($path);
        }
    }

    public function test_single_photo_is_inserted_at_cursor_and_pdf_is_rejected(): void
    {
        Storage::fake('public');
        $page = $this->page();

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->callAction($this->action(), data: ['file' => [UploadedFile::fake()->image('a.jpg')]])
            ->assertHasNoFormErrors()
            ->assertDispatched('run-rich-editor-commands', fn (string $event, array $params): bool => ($params['commands'][0]['arguments'][0]['type'] ?? null) === 'image');

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->callAction($this->action(), data: ['file' => [UploadedFile::fake()->create('nakaz.pdf', 20, 'application/pdf')]])
            ->assertHasErrors();
        Storage::disk('public')->assertDirectoryEmpty('/');
    }

    public function test_replacing_selected_photo_keeps_single_file_update(): void
    {
        Storage::fake('public');
        $page = $this->page();
        $selection = ['type' => 'node', 'anchor' => 1];

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->callAction($this->action(['src' => '/storage/news/old.jpg', 'alt' => 'Старе', 'editorSelection' => $selection]), data: ['alt' => 'Нове'])
            ->assertHasNoFormErrors()
            ->assertDispatched('run-rich-editor-commands', function (string $event, array $params): bool {
                $command = $params['commands'][0] ?? [];

                return ($command['name'] ?? null) === 'updateAttributes'
                    && $command['arguments'][1]['src'] === '/storage/news/old.jpg'
                    && $command['arguments'][1]['alt'] === 'Нове';
            });
    }
}
