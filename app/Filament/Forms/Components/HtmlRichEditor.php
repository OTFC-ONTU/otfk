<?php

namespace App\Filament\Forms\Components;

use DOMDocument;
use DOMElement;
use App\Filament\Forms\Components\RichEditor\EmbedPlugin;
use DOMXPath;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Forms\Components\RichEditor\StateCasts\RichEditorStateCast;
use Filament\Schemas\Components\StateCasts\Contracts\StateCast;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Js;

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
     * з target, зображення з width/height, вирівнювання тексту абзаців і заголовків,
     * <iframe> з усіма атрибутами (вузол EmbedExtension; обгортка <p> навколо нього зникає),
     * рядок заголовка таблиці (thead стає першим рядком tbody з тими самими <th>).
     */
    public const LOSSY = '<(?:video|audio|object|embed|dl|dt|dd|section|article|aside|figure|figcaption|div|span|font|center|caption|colgroup|abbr|cite|q|kbd|ins|form|input|button|svg)\\b'
        .'|<(?!iframe\\b)[a-z][a-z0-9]*\\b[^>]*\\s(?:class|id|align|valign|bgcolor|border|cellpadding|cellspacing)\\s*='
        .'|<(?!(?:p|h[1-6]|img|iframe)\\b)[a-z][a-z0-9]*\\b[^>]*\\sstyle\\s*='
        .'|<(?:p|h[1-6]|img)\\b[^>]*\\sstyle\\s*=\\s*"(?:[^"]*;)?\\s*(?!(?:text-align|width|height)\\s*:)[a-z-]+\\s*:'
        .'|<(?!(?:img|iframe)\\b)[a-z][a-z0-9]*\\b[^>]*\\s(?:width|height)\\s*='
        .'|<a\\b(?![^>]*\\shref\\s*=)'
        .'|<details\\b[^>]*\\sopen\\b'
        .'|\\s(?:srcset|sizes|download|lang|dir)\\s*='
        .'|<!--(?!imported-from:)';

    /** Кнопки розміру зображення: частка колонки сайту (html-editor.js imageSizes) або вся ширина. */
    private const IMAGE_SIZES = ['small' => 'Мале', 'medium' => 'Середнє', 'large' => 'Велике', '' => 'Уся ширина'];

    /** Маркери імпорту (їх читають Page::publicBody(), карта старих адрес, синхронізація). */
    private const MARKER = '<!--imported-from:[^>]*-->';

    protected function setUp(): void
    {
        parent::setUp();

        $this->plugins([EmbedPlugin::make()]);

        // Розмір зображення — кнопками плаваючої панелі (частка колонки сайту) або перетягуванням кутика;
        // width/height зберігаються, пропорції — так само (resources/js/admin/html-editor.js)
        $this->resizableImages();
        $this->tools(array_map(
            fn (string $size, string $label): RichEditorTool => RichEditorTool::make('imageSize'.ucfirst($size ?: 'full'))
                ->label($label)
                ->hiddenLabel(false)
                ->jsHandler('window.otfkHtmlEditor?.setImageSize($getEditor(), '.Js::from($size ?: null).')')
                ->activeJsExpression('window.otfkHtmlEditor?.imageSizeIs($getEditor(), '.Js::from($size ?: null).')'),
            array_keys(self::IMAGE_SIZES),
            self::IMAGE_SIZES,
        ));
        $this->floatingToolbars(fn (self $component): array => [
            ...$component->getDefaultFloatingToolbars(),
            'image' => array_map(fn (string $size) => 'imageSize'.ucfirst($size ?: 'full'), array_keys(self::IMAGE_SIZES)),
        ]);

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
            ['table', 'details', EmbedPlugin::NAME, ...($this->hasFileAttachments(default: true) ? ['attachFiles'] : [])],
            ['clearFormatting', 'undo', 'redo'],
        ];
    }

    /** @return array<StateCast> */
    public function getDefaultStateCasts(): array
    {
        $casts = array_filter(parent::getDefaultStateCasts(), fn (StateCast $cast) => ! $cast instanceof RichEditorStateCast);

        return [...$casts, new HtmlStateCast($this)];
    }

    /**
     * Вкладення документа TipTap обробляє Filament. У HTML-рядку (після переходу в режим HTML)
     * лишаються <img data-id="…"> ще не збережених завантажень із тимчасовою адресою Livewire —
     * їх переносимо в постійне сховище й підставляємо постійну адресу; решту HTML не чіпаємо.
     */
    public function saveFileAttachments(): void
    {
        $state = $this->getRawState();
        if (is_array($state)) {
            parent::saveFileAttachments();

            return;
        }
        if (! is_string($state) || ! str_contains($state, 'data-id=')) {
            return;
        }

        $html = preg_replace_callback('~<img\b[^>]*>~i', function (array $match): string {
            $tag = $match[0];
            if (! preg_match('~\sdata-id\s*=\s*(["\'])([^"\']+)\1~i', $tag, $id) || ! ($attachment = $this->getUploadedFileAttachment($id[2]))) {
                return $tag;
            }
            $path = $this->saveUploadedFileAttachment($attachment);
            $url = $path ? $this->getFileAttachmentUrl($path) : null;
            if (blank($url)) {
                return $tag;
            }

            $tag = (string) preg_replace('~(\sdata-id\s*=\s*)(["\'])[^"\']*\2~i', '$1$2'.e($path).'$2', $tag);

            return (string) preg_replace('~(\ssrc\s*=\s*)(["\'])[^"\']*\2~i', '$1$2'.e($url).'$2', $tag);
        }, $state);

        if ($html !== null && $html !== $state) {
            $this->rawState($html);
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

    /**
     * Сирий стан форми, у якому документи TipTap (правка у візуальному режимі) замінено на HTML —
     * для превʼю незбереженої форми (PreviewFormAction), яке читає стан без дегідратації.
     */
    public static function rawStateWithHtml(Schema $form): array
    {
        $state = (array) $form->getRawState();
        foreach ($form->getFlatFields(withHidden: true) as $field) {
            $path = $field->getStatePath(isAbsolute: false);
            if ($field instanceof self && is_array(data_get($state, $path))) {
                data_set($state, $path, $field->documentToHtml(data_get($state, $path)));
            }
        }

        return $state;
    }

    public static function isLossy(?string $html): bool
    {
        return preg_match('~'.self::LOSSY.'~i', (string) $html) === 1;
    }
}
