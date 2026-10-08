<?php

namespace Tests\Feature;

use App\Filament\Forms\Components\HtmlRichEditor;
use App\Filament\Forms\Components\RichEditor\EmbedPlugin;
use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Models\Page;
use App\Models\User;
use App\Support\EmbedSource;
use App\Support\HtmlSanitizer;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Кнопка «PDF / відео» редактора: посилання нормалізуються в дозволені адреси iframe,
 * вузол embed зберігає наявні iframe імпорту з усіма атрибутами, вставка дає той самий
 * вигляд, що й імпорт (class/style/loading), а iframe більше не робить текст «lossy».
 */
class EditorEmbedTest extends TestCase
{
    use RefreshDatabase;

    public function test_links_are_normalized_to_allowed_iframe_sources(): void
    {
        $video = fn (string $url) => EmbedSource::normalize($url)['src'] ?? null;

        $this->assertSame('https://www.youtube.com/embed/dQw4w9WgXcQ', $video('https://www.youtube.com/watch?v=dQw4w9WgXcQ&list=x'));
        $this->assertSame('https://www.youtube.com/embed/dQw4w9WgXcQ?start=83', $video('https://youtu.be/dQw4w9WgXcQ?t=1m23s'));
        $this->assertSame('https://www.youtube.com/embed/dQw4w9WgXcQ', $video('https://youtube.com/shorts/dQw4w9WgXcQ'));
        $this->assertSame('https://drive.google.com/file/d/1AbC-d_E/preview', $video('https://drive.google.com/file/d/1AbC-d_E/view?usp=sharing'));
        $this->assertSame('https://docs.google.com/presentation/d/1XyZ/preview', $video('https://docs.google.com/presentation/d/1XyZ/edit#slide=1'));
        $this->assertSame('/storage/documents/plan.pdf', $video(rtrim(config('app.url'), '/').'/storage/documents/plan.pdf'));
        $this->assertSame('/storage/mirror/a.pdf', $video('/storage/mirror/a.pdf'));
        $this->assertSame(EmbedSource::KIND_PDF, EmbedSource::normalize('https://otfk.od.ua/files/a.pdf')['kind']);

        $this->assertNull(EmbedSource::normalize('https://example.com/file.pdf'));
        $this->assertNull(EmbedSource::normalize('https://www.google.com/maps/place/Odesa'));
        $this->assertNull(EmbedSource::normalize('javascript:alert(1)'));
        $this->assertNull(EmbedSource::normalize('https://forms.gle/abc'));
        $this->assertNull(EmbedSource::normalize(''));
    }

    public function test_iframes_round_trip_through_editor_document_with_all_attributes(): void
    {
        $html = '<p><iframe src="/storage/mirror/a.pdf" class="w-full" style="min-height:24rem;border:0" loading="lazy"></iframe></p>'
            .'<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ" title="Відео" allowfullscreen></iframe>';

        $this->assertFalse(HtmlRichEditor::isLossy($html));
        $this->assertTrue(HtmlRichEditor::isLossy('<p class="lead"><iframe src="/a.pdf"></iframe></p>'));

        $editor = RichContentRenderer::make()->plugins([EmbedPlugin::make()])->getEditor()->setContent($html);
        $saved = HtmlSanitizer::clean($editor->getHtml());

        $this->assertStringContainsString('src="/storage/mirror/a.pdf"', $saved);
        $this->assertStringContainsString('class="w-full"', $saved);
        $this->assertStringContainsString('loading="lazy"', $saved);
        $this->assertStringContainsString('min-height', $saved);
        $this->assertStringContainsString('title="Відео"', $saved);
        $this->assertStringContainsString('allowfullscreen', $saved);
    }

    public function test_embed_action_inserts_iframe_with_import_look_and_saves_document(): void
    {
        $this->actingAs(User::firstOrFail());
        $page = Page::create(['title' => 'Вбудовування', 'slug' => 'vbudovuvannya', 'body' => '<p>Текст</p>', 'is_published' => true]);

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->callAction(TestAction::make(EmbedPlugin::NAME)->schemaComponent('body', schema: 'form'), data: ['url' => 'https://example.com/a.pdf'])
            ->assertHasFormErrors(['url']);

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->callAction(
                TestAction::make(EmbedPlugin::NAME)->schemaComponent('body', schema: 'form')->arguments(['editorSelection' => ['type' => 'text', 'anchor' => 1, 'head' => 1]]),
                data: ['url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'title' => 'Екскурсія'],
            )
            ->assertHasNoFormErrors()
            ->assertDispatched('run-rich-editor-commands', function (string $event, array $params): bool {
                $attrs = $params['commands'][0]['arguments'][0]['attrs'] ?? [];

                return ($params['commands'][0]['name'] ?? null) === 'insertContent'
                    && $attrs === EmbedPlugin::attributes(['src' => 'https://www.youtube.com/embed/dQw4w9WgXcQ', 'kind' => EmbedSource::KIND_VIDEO], 'Екскурсія')
                    && $attrs['class'] === 'w-full' && isset($attrs['allowfullscreen']);
            });

        // Документ, який надішле TipTap після вставки, зберігається як HTML з iframe
        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->set('data.body', ['type' => 'doc', 'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Текст']]],
                ['type' => 'embed', 'attrs' => EmbedPlugin::attributes(['src' => '/storage/documents/plan.pdf', 'kind' => EmbedSource::KIND_PDF], 'План')],
            ]])
            ->call('save')
            ->assertHasNoFormErrors();

        $body = $page->fresh()->body;
        $this->assertStringContainsString('<iframe src="/storage/documents/plan.pdf" title="План" class="w-full"', $body);
        $this->assertFalse(HtmlRichEditor::isLossy($body), $body);
    }

    public function test_uploaded_pdf_gets_unique_readable_name_and_does_not_overwrite(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $this->actingAs(User::firstOrFail());
        $page = Page::create(['title' => 'PDF', 'slug' => 'pdf-test', 'body' => '<p>x</p>', 'is_published' => true]);
        $sources = [];

        foreach ([1, 2] as $attempt) {
            Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
                ->callAction(
                    TestAction::make(EmbedPlugin::NAME)->schemaComponent('body', schema: 'form')->arguments(['editorSelection' => ['type' => 'text', 'anchor' => 1, 'head' => 1]]),
                    data: ['file' => \Illuminate\Http\UploadedFile::fake()->create('Наказ №1.pdf', 20, 'application/pdf')],
                )
                ->assertHasNoFormErrors()
                ->assertDispatched('run-rich-editor-commands', function (string $event, array $params) use (&$sources): bool {
                    $sources[] = $params['commands'][0]['arguments'][0]['attrs']['src'] ?? '';

                    return true;
                });
        }

        $this->assertCount(2, array_unique($sources));
        foreach ($sources as $src) {
            $this->assertMatchesRegularExpression('~^/storage/documents/vbudovani/nakaz-1-[a-z0-9]{6}\.pdf$~', $src);
            \Illuminate\Support\Facades\Storage::disk('public')->assertExists(substr($src, strlen('/storage/')));
        }
    }
}
