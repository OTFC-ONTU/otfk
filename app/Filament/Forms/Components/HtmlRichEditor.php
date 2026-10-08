<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\RichEditor;
use Illuminate\Support\HtmlString;

/**
 * Візуальний редактор (Trix) з перемикачем «Візуально / HTML».
 *
 * У режимі HTML поле — звичайний textarea з тим самим станом: розмітку можна копіювати
 * (наприклад, для англійського перекладу) і вставляти. Trix спрощує таблиці, details,
 * iframe та атрибути class/style, тому такий вміст одразу відкривається в режимі HTML,
 * а зміни Trix у цьому режимі не записуються в стан.
 */
class HtmlRichEditor extends RichEditor
{
    protected string $view = 'filament.forms.components.html-rich-editor';

    /** Розмітка, яку Trix не зберігає без втрат. */
    private const TRIX_LOSSY = '~<(?:table|details|summary|iframe|video|audio|object|embed|dl|section|article|aside|h[1456]|span|font|hr|sup|sub|u|s|small|mark|code)\b|<[a-z][^>]*\s(?:class|style|id|target|width|height|align)\s*=|<!--~i';

    protected function setUp(): void
    {
        parent::setUp();

        $this->hint(fn (self $component): ?HtmlString => $component->isDisabled()
            ? null
            : new HtmlString(view('filament.forms.components.html-rich-editor-toggle')->render()));
    }

    public static function needsHtmlMode(?string $html): bool
    {
        // Вкладення, вставлені самим Trix, він відтворює без втрат
        $html = preg_replace('~<figure\b[^>]*data-trix-attachment.*?</figure>~is', '', (string) $html) ?? '';

        return preg_match(self::TRIX_LOSSY, $html) === 1
            || preg_match('~<img\b~i', $html) === 1;
    }
}
