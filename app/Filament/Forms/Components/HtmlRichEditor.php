<?php

namespace App\Filament\Forms\Components;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\StateCasts\RichEditorStateCast;
use Filament\Schemas\Components\StateCasts\Contracts\StateCast;
use Illuminate\Support\HtmlString;

/**
 * Візуальний редактор (TipTap, RichEditor Filament 4) з перемикачем «Візуально / HTML».
 *
 * Стан поля — завжди HTML-рядок як у БД: стандартний RichEditor перетворює HTML на документ
 * TipTap уже під час завантаження й зберігає назад нормалізований HTML, тобто прибирав би
 * iframe, class/style, коментарі тощо навіть без правок. Тут у HTML перетворюється лише
 * документ, який надіслав сам TipTap після правки користувача у візуальному режимі
 * (HtmlStateCast); режим HTML (CodeMirror) пише рядок без змін. Для вмісту, який TipTap
 * спрощує (isLossy()), перша дія у візуальному режимі потребує підтвердження.
 */
class HtmlRichEditor extends RichEditor
{
    protected string $view = 'filament.forms.components.html-rich-editor';

    /**
     * Розмітка, яку TipTap не зберігає без втрат (без розділювачів — спільна для PHP і JS).
     * Зберігаються: заголовки, списки, цитати, таблиці (colspan/rowspan), details, посилання
     * з target, зображення з width/height, вирівнювання тексту абзаців і заголовків.
     */
    public const LOSSY = '<(?:iframe|video|audio|object|embed|dl|dt|dd|section|article|aside|figure|figcaption|div|span|font|center|caption|colgroup|abbr|cite|q|kbd|ins|form|input|button|svg)\\b'
        .'|<[a-z][a-z0-9]*\\b[^>]*\\s(?:class|id|align|valign|bgcolor|border|cellpadding|cellspacing)\\s*='
        .'|<(?!(?:p|h[1-6]|img)\\b)[a-z][a-z0-9]*\\b[^>]*\\sstyle\\s*='
        .'|\\sstyle\\s*=\\s*"(?:[^"]*;)?\\s*(?!(?:text-align|width|height)\\s*:)[a-z-]+\\s*:'
        .'|<(?!img\\b)[a-z][a-z0-9]*\\b[^>]*\\s(?:width|height)\\s*='
        .'|<!--(?!imported-from:)';

    /** Маркери імпорту (їх читають Page::publicBody(), карта старих адрес, синхронізація). */
    private const MARKER = '<!--imported-from:[^>]*-->';

    protected function setUp(): void
    {
        parent::setUp();

        $this->hint(fn (self $component): ?HtmlString => $component->isDisabled()
            ? null
            : new HtmlString(view('filament.forms.components.html-rich-editor-toggle')->render()));
    }

    public function getDefaultToolbarButtons(): array
    {
        return [
            ['bold', 'italic', 'underline', 'strike', 'subscript', 'superscript', 'link'],
            ['h2', 'h3', 'h4'],
            ['alignStart', 'alignCenter', 'alignEnd', 'alignJustify'],
            ['blockquote', 'bulletList', 'orderedList', 'horizontalRule'],
            ['table', 'details', ...($this->hasFileAttachments(default: true) ? ['attachFiles'] : [])],
            ['clearFormatting', 'undo', 'redo'],
        ];
    }

    /** @return array<StateCast> */
    public function getDefaultStateCasts(): array
    {
        $casts = array_filter(parent::getDefaultStateCasts(), fn (StateCast $cast) => ! $cast instanceof RichEditorStateCast);

        return [...$casts, new HtmlStateCast($this)];
    }

    /** Вкладення обробляються лише в документі TipTap; HTML-рядок не перетворюємо. */
    public function saveFileAttachments(): void
    {
        if (is_array($this->getRawState())) {
            parent::saveFileAttachments();
        }
    }

    /** Документ TipTap → HTML (як у RichEditor) з маркерами імпорту з наявного запису. */
    public function documentToHtml(array $document): string
    {
        $html = self::cleanEditorHtml((string) (new RichEditorStateCast($this))->get($document));

        $original = (string) $this->getRecord()?->getOriginal($this->getName());
        preg_match_all('~'.self::MARKER.'~', $original, $markers);
        foreach (array_unique($markers[0]) as $marker) {
            if (! str_contains($html, $marker)) {
                $html .= "\n".$marker;
            }
        }

        return $html;
    }

    /**
     * Службова розмітка TipTap, якої немає в ручному HTML: обгортка вмісту розгортного блоку,
     * colgroup/min-width таблиць, colspan/rowspan="1", типове вирівнювання text-align: start.
     * (Те саме робить cleanEditorHtml() у resources/js/admin/html-editor.js.)
     */
    public static function cleanEditorHtml(string $html): string
    {
        if (str_contains($html, 'data-type="detailsContent"')) {
            $dom = new DOMDocument;
            $previous = libxml_use_internal_errors(true);
            $dom->loadHTML('<?xml encoding="UTF-8"><div id="otfk-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            $xpath = new DOMXPath($dom);
            $root = $xpath->query('//*[@id="otfk-root"]')->item(0);
            if ($root instanceof DOMElement) {
                foreach (iterator_to_array($xpath->query('//div[@data-type="detailsContent"]')) as $wrapper) {
                    while ($wrapper->firstChild) {
                        $wrapper->parentNode->insertBefore($wrapper->firstChild, $wrapper);
                    }
                    $wrapper->parentNode->removeChild($wrapper);
                }
                $html = implode('', array_map(fn ($node) => $dom->saveHTML($node), iterator_to_array($root->childNodes)));
            }
        }

        return (string) preg_replace(
            ['~<colgroup>.*?</colgroup>~s', '~\s(?:colspan|rowspan)="1"~', '~\sstyle="(?:min-width|text-align):\s*(?:\d+px|start);?"~'],
            '',
            $html,
        );
    }

    public static function isLossy(?string $html): bool
    {
        return preg_match('~'.self::LOSSY.'~i', (string) $html) === 1;
    }
}
