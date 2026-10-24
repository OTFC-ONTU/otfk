// Інтервал і відступ блоків для HtmlRichEditor: атрибути data-spacing (абзаци, заголовки, списки)
// і data-indent (абзаци, списки). У HTML зберігається лише назва пресета, розміри для комп'ютера
// й телефона задає оформлення сайту (.prose-site у resources/css/app.css).
// Пара — App\Filament\Forms\Components\RichEditor\BlockSpacingExtension (ті самі типи й значення);
// команди панелі — setBlockSpacing()/changeIndent() у resources/js/admin/html-editor.js.
// Завантажується Filament як ES-модуль (RichContentPlugin::getTipTapJsExtensions()).

const SPACING = ['compact', 'loose']
const INDENT = ['1', '2', '3']

const attribute = (name, allowed) => ({
    default: null,
    parseHTML: (element) => (allowed.includes(element.getAttribute(name)) ? element.getAttribute(name) : null),
    renderHTML: (attributes) => {
        const key = name === 'data-spacing' ? 'spacing' : 'indent'

        return attributes[key] == null ? {} : { [name]: attributes[key] }
    },
})

export default function () {
    const { Extension } = window.FilamentRichEditor.tiptap.core

    return Extension.create({
        name: 'blockSpacing',

        addGlobalAttributes() {
            return [
                { types: ['paragraph', 'heading', 'bulletList', 'orderedList'], attributes: { spacing: attribute('data-spacing', SPACING) } },
                { types: ['paragraph', 'bulletList', 'orderedList'], attributes: { indent: attribute('data-indent', INDENT) } },
            ]
        },
    })
}
