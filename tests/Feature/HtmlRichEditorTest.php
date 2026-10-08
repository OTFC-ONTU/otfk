<?php

namespace Tests\Feature;

use App\Filament\Forms\Components\HtmlRichEditor;
use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Редактор основного тексту (TipTap Filament 4 + режим HTML): стан — HTML як у БД,
 * збереження без правок нічого не нормалізує; документ TipTap (правка у візуальному
 * режимі) перетворюється на HTML із маркерами імпорту; вміст, який TipTap спростив би,
 * позначається попередженням і підтвердженням першої правки.
 */
class HtmlRichEditorTest extends TestCase
{
    use RefreshDatabase;

    public function test_lossy_markup_is_detected(): void
    {
        $this->assertFalse(HtmlRichEditor::isLossy('<p>Текст із <strong>жирним</strong> і <a href="/novyny" target="_blank">посиланням</a>.</p><ul><li>Пункт</li></ul>'));
        $this->assertFalse(HtmlRichEditor::isLossy('<table><tbody><tr><th>A</th><td colspan="2">1</td></tr></tbody></table>'));
        $this->assertFalse(HtmlRichEditor::isLossy('<details><summary>Більше</summary><p>…</p></details>'));
        $this->assertFalse(HtmlRichEditor::isLossy('<p style="text-align: center;"><img src="/storage/a.png" alt="Фото" width="300" style="width: 300px; height: 200px;" height="200"></p>'));
        $this->assertFalse(HtmlRichEditor::isLossy('<h4>Розділ</h4><p>Текст</p><!--imported-from:https://otfk.od.ua/x-->'));
        $this->assertFalse(HtmlRichEditor::isLossy(null));

        $this->assertTrue(HtmlRichEditor::isLossy('<p class="lead">Вступ</p>'));
        $this->assertTrue(HtmlRichEditor::isLossy('<p style="font-size: 14pt; text-align: center">Текст</p>'));
        $this->assertTrue(HtmlRichEditor::isLossy('<table style="width:100%"><tr><td>1</td></tr></table>'));
        $this->assertTrue(HtmlRichEditor::isLossy('<td width="30%">1</td>'));
        $this->assertTrue(HtmlRichEditor::isLossy('<iframe src="https://www.youtube.com/embed/x"></iframe>'));
        $this->assertTrue(HtmlRichEditor::isLossy('<div><p>Обгортка</p></div>'));
        $this->assertTrue(HtmlRichEditor::isLossy('<p>Текст</p><!-- примітка -->'));
    }

    public function test_page_editor_has_mode_toggle_and_keeps_html_without_normalization(): void
    {
        $this->actingAs(User::firstOrFail());
        $body = '<p class="lead">Вступ</p><table style="width:100%"><tbody><tr><td>1</td></tr></tbody></table>';
        $page = Page::create(['title' => 'Сторінка з таблицею', 'slug' => 'storinka-z-tablytseyu', 'body' => $body, 'is_published' => true]);
        $stored = $page->fresh()->body;

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->assertSeeHtml('otfk-mode-toggle')
            ->assertSeeHtml("mode: 'visual'")
            ->assertSeeHtml('otfk-lossy-note')
            ->assertSeeHtml('otfk-html-tools')
            ->assertSeeHtml('insertDetails()')
            ->assertSeeHtml('sectionsToDetails()')
            ->assertSet('data.body', $stored)
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame($stored, $page->fresh()->body);

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->fillForm(['body' => '<p class="lead">Вступ</p><table style="width:100%"><tbody><tr><td>2</td></tr></tbody></table>'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertStringContainsString('<table style="width: 100%">', $page->fresh()->body);
        $this->assertStringContainsString('<td>2</td>', $page->fresh()->body);
    }

    public function test_tiptap_document_is_saved_as_html_with_import_markers(): void
    {
        $this->actingAs(User::firstOrFail());
        $page = Page::create([
            'title' => 'Імпортована',
            'slug' => 'importovana',
            'body' => '<p>Старий текст</p><!--imported-from:https://otfk.od.ua/x/-->',
            'is_published' => true,
        ]);

        $document = ['type' => 'doc', 'content' => [
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Новий розділ']]],
            ['type' => 'details', 'content' => [
                ['type' => 'detailsSummary', 'content' => [['type' => 'text', 'text' => 'Блок']]],
                ['type' => 'detailsContent', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Вміст']]]]],
            ]],
        ]];

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->set('data.body', $document)
            ->call('save')
            ->assertHasNoFormErrors();

        $body = $page->fresh()->body;
        $this->assertStringContainsString('<h2>Новий розділ</h2>', $body);
        $this->assertStringContainsString('<details><summary>Блок</summary><p>Вміст</p></details>', $body);
        $this->assertFalse(HtmlRichEditor::isLossy($body), $body);
        $this->assertStringContainsString('<!--imported-from:https://otfk.od.ua/x/-->', $body);
    }

    public function test_editor_service_markup_is_removed_including_nested_blocks(): void
    {
        $html = '<details><summary>Зовнішній</summary><div data-type="detailsContent"><p>a &amp; b</p>'
            .'<details><summary>Вкладений</summary><div data-type="detailsContent"><p>ї<br><img src="/a.jpg" alt="Фото"></p></div></details></div></details>'
            .'<table style="min-width: 50px;"><colgroup><col style="min-width: 25px;"></colgroup><tbody><tr><td colspan="1" rowspan="2"><p style="text-align: start;">x</p></td></tr></tbody></table>';

        $this->assertSame(
            '<details><summary>Зовнішній</summary><p>a &amp; b</p><details><summary>Вкладений</summary><p>ї<br><img src="/a.jpg" alt="Фото"></p></details></details>'
            .'<table><tbody><tr><td rowspan="2"><p>x</p></td></tr></tbody></table>',
            HtmlRichEditor::cleanEditorHtml($html),
        );
    }
}
