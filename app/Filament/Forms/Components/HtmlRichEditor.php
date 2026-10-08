<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\RichEditor;
use Illuminate\Support\HtmlString;

/**
 * Візуальний редактор (Trix) з перемикачем «Візуально / HTML».
 *
 * Типово відкривається візуальний режим; у режимі HTML поле — звичайний textarea з тим
 * самим станом: розмітку можна копіювати (наприклад, для англійського перекладу) і вставляти.
 * Trix нормалізує текст уже під час завантаження й спрощує таблиці, details, iframe та
 * атрибути class/style, тому його зміни потрапляють у стан лише після власної дії
 * користувача у візуальному редакторі; для такого вмісту перша дія потребує підтвердження.
 */
class HtmlRichEditor extends RichEditor
{
    protected string $view = 'filament.forms.components.html-rich-editor';

    /** Розмітка, яку Trix не зберігає без втрат (без розділювачів — спільна для PHP і JS). */
    public const TRIX_LOSSY = '<(?:table|details|summary|iframe|video|audio|object|embed|dl|section|article|aside|h[1456]|span|font|hr|sup|sub|u|s|small|mark|code|img)\\b|<[a-z][^>]*\\s(?:class|style|id|target|width|height|align)\\s*=|<!--';

    /** Вкладення, вставлені самим Trix, він відтворює без втрат. */
    public const TRIX_ATTACHMENT = '<figure\\b[^>]*data-trix-attachment[\\s\\S]*?</figure>';

    protected function setUp(): void
    {
        parent::setUp();

        $this->hint(fn (self $component): ?HtmlString => $component->isDisabled()
            ? null
            : new HtmlString(view('filament.forms.components.html-rich-editor-toggle')->render()));
    }

    public static function isTrixLossy(?string $html): bool
    {
        $html = preg_replace('~'.self::TRIX_ATTACHMENT.'~i', '', (string) $html) ?? '';

        return preg_match('~'.self::TRIX_LOSSY.'~i', $html) === 1;
    }
}
