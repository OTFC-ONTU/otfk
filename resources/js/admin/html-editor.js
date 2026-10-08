// Завантажувач редактора HTML в адмінці: форматування — одразу, сам CodeMirror — окремим
// чанком лише при першому переході поля в режим «HTML» (App\Filament\Forms\Components\HtmlRichEditor).
import { formatHtml } from './html-format.js'
import { detailsSnippet, headingsToDetails } from './html-sections.js'

window.otfkHtmlEditor = {
    formatHtml,
    detailsSnippet,
    headingsToDetails,

    /**
     * HTML із TipTap (після правки у візуальному режимі) у вигляді, як його зберігає сервер:
     * без обгортки вмісту розгортного блоку, colgroup/min-width таблиць, colspan/rowspan="1"
     * і типового вирівнювання text-align: start (як HtmlRichEditor::cleanEditorHtml()).
     */
    cleanEditorHtml(html) {
        const template = document.createElement('template')
        template.innerHTML = html
        template.content.querySelectorAll('div[data-type="detailsContent"]').forEach((div) => div.replaceWith(...div.childNodes))
        template.content.querySelectorAll('colgroup').forEach((element) => element.remove())
        template.content.querySelectorAll('[colspan="1"], [rowspan="1"]').forEach((element) => {
            if (element.getAttribute('colspan') === '1') element.removeAttribute('colspan')
            if (element.getAttribute('rowspan') === '1') element.removeAttribute('rowspan')
        })
        template.content.querySelectorAll('[style]').forEach((element) => {
            if (/^\s*(?:text-align:\s*start|min-width:\s*\d+px);?\s*$/i.test(element.getAttribute('style'))) element.removeAttribute('style')
        })

        return template.innerHTML
    },

    /** Ширина колонки тексту на сайті (≈685–840 px) — від неї рахуються розміри зображень. */
    imageColumn: 720,

    /** Частки колонки для кнопок розміру зображення (плаваюча панель HtmlRichEditor). */
    imageSizes: { small: 1 / 3, medium: 1 / 2, large: 3 / 4 },

    /** Виділене зображення: вузол, позиція й елемент <img> у редакторі. */
    selectedImage(editor) {
        const selection = editor?.state.selection
        if (! selection?.node || selection.node.type.name !== 'image') return null
        const dom = editor.view.nodeDOM(selection.from)

        return { node: selection.node, pos: selection.from, img: dom?.tagName === 'IMG' ? dom : dom?.querySelector?.('img') }
    },

    /**
     * Розмір виділеного зображення: частка колонки сайту (не більше власної ширини файлу)
     * або null — на всю ширину колонки. Пропорції зберігаються.
     */
    setImageSize(editor, size) {
        const image = this.selectedImage(editor)
        if (! image) return
        const naturalWidth = image.img?.naturalWidth || 0
        const naturalHeight = image.img?.naturalHeight || 0
        let width = null
        let height = null
        if (size && this.imageSizes[size]) {
            width = Math.round(this.imageColumn * this.imageSizes[size])
            if (naturalWidth && width > naturalWidth) width = naturalWidth
            height = naturalWidth ? Math.round(width * naturalHeight / naturalWidth) : null
        }
        editor.chain().focus().updateAttributes('image', { width, height }).setNodeSelection(image.pos).run()
        // Вузол із ручками розміру не перечитує width/height після зміни атрибутів — оновлюємо сам елемент
        if (image.img) {
            image.img.style.width = width ? `${width}px` : ''
            image.img.style.height = height ? `${height}px` : ''
        }
    },

    imageSizeIs(editor, size) {
        const width = Number(this.selectedImage(editor)?.node.attrs.width) || null
        if (! size) return width === null

        return width !== null && Math.abs(width - Math.round(this.imageColumn * this.imageSizes[size])) <= 1
    },

    /**
     * @param {HTMLElement} parent
     * @param {{ doc: string, label: string, onChange: (value: string) => void }} options
     */
    async mount(parent, options) {
        const { createEditor } = await import('./html-editor-codemirror.js')

        return createEditor(parent, options)
    },
}
