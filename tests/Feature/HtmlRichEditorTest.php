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
 * Перемикач «Візуально / HTML» у редакторах основного тексту: розмітку можна
 * копіювати й вставляти; типово — візуальний режим, а вміст, який Trix спростив би,
 * не перезаписується без дії користувача (попередження + підтвердження першої правки).
 */
class HtmlRichEditorTest extends TestCase
{
    use RefreshDatabase;

    public function test_trix_lossy_markup_is_detected(): void
    {
        $this->assertFalse(HtmlRichEditor::isTrixLossy('<p>Текст із <strong>жирним</strong> і <a href="/novyny">посиланням</a>.</p><ul><li>Пункт</li></ul>'));
        $this->assertFalse(HtmlRichEditor::isTrixLossy('<figure data-trix-attachment="{}" class="attachment"><img src="/storage/pages/a.jpg"></figure>'));
        $this->assertFalse(HtmlRichEditor::isTrixLossy(null));

        $this->assertTrue(HtmlRichEditor::isTrixLossy('<table><tr><td>1</td></tr></table>'));
        $this->assertTrue(HtmlRichEditor::isTrixLossy('<details><summary>Більше</summary><p>…</p></details>'));
        $this->assertTrue(HtmlRichEditor::isTrixLossy('<p class="lead">Вступ</p>'));
        $this->assertTrue(HtmlRichEditor::isTrixLossy('<p><img src="/storage/mirror/a.png"></p>'));
        $this->assertTrue(HtmlRichEditor::isTrixLossy('<p>Текст</p><!--imported-from:https://otfk.od.ua/x-->'));
    }

    public function test_page_editor_has_mode_toggle_and_keeps_html_on_save(): void
    {
        $this->actingAs(User::firstOrFail());
        $page = Page::create([
            'title' => 'Сторінка з таблицею',
            'slug' => 'storinka-z-tablytseyu',
            'body' => '<p>Вступ</p><table><tbody><tr><td>1</td></tr></tbody></table>',
            'is_published' => true,
        ]);

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->assertSeeHtml('otfk-mode-toggle')
            ->assertSeeHtml("mode: 'visual'")
            ->assertSeeHtml('otfk-lossy-note')
            ->assertSeeHtml('otfk-html-tools')
            ->assertSeeHtml('insertDetails()')
            ->assertSeeHtml('sectionsToDetails()')
            ->fillForm(['body' => '<p>Вступ</p><table><tbody><tr><td>2</td></tr></tbody></table>'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertStringContainsString('<td>2</td>', $page->fresh()->body);
    }
}
