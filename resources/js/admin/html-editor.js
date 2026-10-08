// Завантажувач редактора HTML в адмінці: форматування — одразу, сам CodeMirror — окремим
// чанком лише при першому переході поля в режим «HTML» (App\Filament\Forms\Components\HtmlRichEditor).
import { formatHtml } from './html-format.js'
import { detailsSnippet, headingsToDetails } from './html-sections.js'

window.otfkHtmlEditor = {
    formatHtml,
    detailsSnippet,
    headingsToDetails,

    /**
     * @param {HTMLElement} parent
     * @param {{ doc: string, label: string, onChange: (value: string) => void }} options
     */
    async mount(parent, options) {
        const { createEditor } = await import('./html-editor-codemirror.js')

        return createEditor(parent, options)
    },
}
