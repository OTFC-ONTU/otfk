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

    /**
     * @param {HTMLElement} parent
     * @param {{ doc: string, label: string, onChange: (value: string) => void }} options
     */
    async mount(parent, options) {
        const { createEditor } = await import('./html-editor-codemirror.js')

        return createEditor(parent, options)
    },
}
