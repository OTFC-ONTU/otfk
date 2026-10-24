<?php

namespace Tests\Feature;

use App\Filament\Forms\Components\HtmlRichEditor;
use App\Filament\Forms\Components\RichEditor\BlockSpacingPlugin;
use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Models\Page;
use App\Models\User;
use App\Support\HtmlSanitizer;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Пресети інтервалу (data-spacing) і відступу (data-indent) редактора та кнопка «Виправити вставлений
 * текст»: атрибути переживають документ TipTap і очищення HTML, невідомі значення відкидаються,
 * текст із пресетами не «lossy», а сайт має для них оформлення комп'ютера й телефона.
 * Логіку самої кнопки виправлення перевіряє tests/Frontend/HtmlPasteFixTest.mjs.
 */
class EditorBlockSpacingTest extends TestCase
{
    use RefreshDatabase;

    private const HTML = '<p data-spacing="compact">Щільно</p><h3 data-spacing="loose">Розділ</h3>'
        .'<ul data-spacing="loose" data-indent="2"><li><p>Пункт</p></li></ul><p data-indent="1" style="text-align: justify">Відступ</p>';

    public function test_presets_round_trip_through_editor_document_and_sanitizer(): void
    {
        $this->assertFalse(HtmlRichEditor::isLossy(self::HTML));

        $editor = RichContentRenderer::make()->plugins([BlockSpacingPlugin::make()])->getEditor()->setContent(self::HTML);
        $document = $editor->getDocument();
        $this->assertSame('compact', $document['content'][0]['attrs']['spacing']);
        $this->assertSame('2', $document['content'][2]['attrs']['indent']);

        $saved = HtmlSanitizer::clean($editor->getHtml());
        $this->assertStringContainsString('<p data-spacing="compact">Щільно</p>', $saved);
        $this->assertStringContainsString('<h3 data-spacing="loose">Розділ</h3>', $saved);
        $this->assertMatchesRegularExpression('~<ul (?=[^>]*data-spacing="loose")(?=[^>]*data-indent="2")~', $saved);
        $this->assertMatchesRegularExpression('~<p (?=[^>]*data-indent="1")(?=[^>]*text-align: justify)~', $saved);
    }

    public function test_unknown_values_and_elements_are_dropped(): void
    {
        $clean = HtmlSanitizer::clean('<p data-spacing="huge" data-indent="9">a</p><div data-spacing="loose">b</div><p data-indent=" 3 ">c</p>');

        $this->assertStringNotContainsString('huge', $clean);
        $this->assertStringNotContainsString('data-indent="9"', $clean);
        $this->assertStringNotContainsString('<div data-spacing', $clean);
        $this->assertStringContainsString('<p data-indent="3">c</p>', $clean);

        $editor = RichContentRenderer::make()->plugins([BlockSpacingPlugin::make()])->getEditor()->setContent('<p data-spacing="huge">a</p>');
        $this->assertSame('<p>a</p>', $editor->getHtml());
    }

    public function test_editor_has_spacing_indent_and_fix_buttons_and_saves_presets(): void
    {
        $this->actingAs(User::firstOrFail());
        $page = Page::create(['title' => 'Інтервали', 'slug' => 'intervaly', 'body' => '<p>Текст</p>', 'is_published' => true]);

        $test = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->assertSeeHtml('otfkHtmlEditor?.setBlockSpacing($getEditor(), \'compact\')')
            ->assertSeeHtml('otfkHtmlEditor?.setBlockSpacing($getEditor(), \'loose\')')
            ->assertSeeHtml('otfkHtmlEditor?.changeIndent($getEditor(), 1)')
            ->assertSeeHtml('otfkHtmlEditor?.changeIndent($getEditor(), -1)')
            ->assertSeeHtml('otfkHtmlEditor?.fixPastedText($getEditor())')
            ->assertSeeHtml('rich-editor-spacing.js');

        $test->set('data.body', ['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'attrs' => ['spacing' => 'loose', 'indent' => '1'], 'content' => [['type' => 'text', 'text' => 'Абзац']]],
            ['type' => 'orderedList', 'attrs' => ['start' => 1, 'spacing' => 'compact'], 'content' => [
                ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Один']]]]],
            ]],
        ]])
            ->call('save')
            ->assertHasNoFormErrors();

        $body = $page->fresh()->body;
        $this->assertMatchesRegularExpression('~<p (?=[^>]*data-spacing="loose")(?=[^>]*data-indent="1")[^>]*>Абзац</p>~', $body);
        $this->assertMatchesRegularExpression('~<ol [^>]*data-spacing="compact"~', $body);
        $this->assertFalse(HtmlRichEditor::isLossy($body), $body);
    }

    public function test_site_styles_define_presets_with_smaller_mobile_values(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('[data-spacing="compact"]', $css);
        $this->assertStringContainsString('[data-spacing="loose"]', $css);
        foreach (['1', '2', '3'] as $level) {
            $this->assertStringContainsString('[data-indent="'.$level.'"]', $css);
        }
        // Телефон — менший крок відступу, ширші значення лише з @media (min-width: 640px)
        $this->assertMatchesRegularExpression('~\.prose-site \{[^}]*--otfk-indent-step: 1rem~', $css);
        $this->assertMatchesRegularExpression('~@media \(min-width: 640px\) \{ \.prose-site \{[^}]*--otfk-indent-step: 2\.5rem~', $css);
    }
}
