// Завантажувач редактора HTML в адмінці: форматування — одразу, сам CodeMirror — окремим
// чанком лише при першому переході поля в режим «HTML» (App\Filament\Forms\Components\HtmlRichEditor).
import { formatHtml } from './html-format.js'
import { detailsSnippet, headingsToDetails } from './html-sections.js'
import { fixPastedBlocks } from './html-paste-fix.js'

/** Блоки з інтервалом і відступом (public/js/admin/rich-editor-spacing.js, BlockSpacingExtension). */
const SPACING_TYPES = ['paragraph', 'heading', 'bulletList', 'orderedList']
const INDENT_TYPES = ['paragraph', 'bulletList', 'orderedList']
const LISTS = ['bulletList', 'orderedList']
const MAX_INDENT = 3

/** Сповіщення панелі Filament (без нього — нічого не показуємо). */
const notify = (title, status = 'info') => {
    if (window.FilamentNotification) new window.FilamentNotification().title(title)[status]().send()
}

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
     * Кнопка «Розгортний блок» візуального режиму: виділені блоки стають вмістом блоку, а
     * заголовок на їх початку (або перший абзац, якщо виділено кілька блоків) — його підписом,
     * як detailsSnippet() у режимі HTML. Інші випадки — стандартна команда TipTap setDetails().
     */
    wrapInDetails(editor) {
        const state = editor?.state
        const { details, detailsSummary, detailsContent, paragraph } = state?.schema.nodes ?? {}
        const range = state?.selection.$from.blockRange(state.selection.$to)
        if (! details || ! detailsSummary || ! detailsContent || ! range) return editor?.chain().focus().setDetails().run()

        const blocks = []
        for (let index = range.startIndex; index < range.endIndex; index++) blocks.push(range.parent.child(index))
        if (! detailsContent.contentMatch.matchFragment(state.doc.slice(range.start, range.end).content)) {
            return editor.chain().focus().setDetails().run()
        }

        const first = blocks[0]
        const titled = first.type.name === 'heading' || (first.type.name === 'paragraph' && blocks.length > 1)
        const summary = titled ? this.summaryText(first, detailsSummary) : null
        // Порожні абзаци між підписом і вмістом не переносимо
        const body = summary ? blocks.slice(1) : blocks
        while (summary && body.length && body[0].type.name === 'paragraph' && body[0].content.size === 0) body.shift()
        const node = details.create(null, [
            detailsSummary.create(null, summary ?? []),
            detailsContent.create(null, body.length ? body : [paragraph.create()]),
        ])

        const tr = state.tr.replaceWith(range.start, range.end, node)
        const cursor = range.start + 2 + (summary ? node.firstChild.content.size : 0)
        editor.view.dispatch(tr.setSelection(state.selection.constructor.near(tr.doc.resolve(cursor))).scrollIntoView())
        editor.view.focus()

        return true
    },

    /** Текст блоку для підпису (лише текст із дозволеними позначками; інші вкладені вузли — ні). */
    summaryText(block, summaryType) {
        const nodes = []
        let plain = true
        block.forEach((inline) => {
            if (inline.isText) nodes.push(inline.mark(inline.marks.filter((mark) => summaryType.allowsMarkType(mark.type))))
            else if (inline.type.name === 'hardBreak') nodes.push(block.type.schema.text(' '))
            else plain = false
        })

        return plain && nodes.length ? nodes : null
    },

    /**
     * Кнопки H2–H4: у підписі розгортного блоку (там лише текст) блок розгортається назад —
     * підпис стає заголовком цього рівня, вміст блоку — абзацами під ним; інакше toggleHeading().
     */
    heading(editor, level) {
        const state = editor?.state
        const $from = state?.selection.$from
        const heading = state?.schema.nodes.heading
        if (! $from || ! heading || $from.parent.type.name !== 'detailsSummary' || $from.depth < 2) {
            return editor?.chain().focus().toggleHeading({ level }).run()
        }

        const block = $from.node($from.depth - 1)
        const pos = $from.before($from.depth - 1)
        const nodes = [heading.create({ level }, $from.parent.content)]
        block.forEach((child) => {
            if (child.type.name === 'detailsContent') child.forEach((node) => nodes.push(node))
        })

        const tr = state.tr.replaceWith(pos, pos + block.nodeSize, nodes)
        const cursor = pos + 1 + Math.min($from.parentOffset, nodes[0].content.size)
        editor.view.dispatch(tr.setSelection(state.selection.constructor.near(tr.doc.resolve(cursor))).scrollIntoView())
        editor.view.focus()

        return true
    },

    /**
     * Блоки, до яких застосовуються інтервал і відступ: виділені абзаци/заголовки, а якщо рядок
     * у списку — сам список (найближчий), щоб змінювалися відстані між пунктами й відступ усього списку.
     *
     * @returns {Array<{ pos: number, node: object }>}
     */
    spacingTargets(editor, attribute) {
        const state = editor?.state
        if (! state) return []
        const types = attribute === 'spacing' ? SPACING_TYPES : INDENT_TYPES
        const targets = new Map()
        const add = (node, pos) => {
            const $pos = state.doc.resolve(pos + 1)
            for (let depth = $pos.depth - 1; depth > 0; depth--) {
                if (LISTS.includes($pos.node(depth).type.name)) {
                    targets.set($pos.before(depth), $pos.node(depth))
                    return
                }
            }
            if (types.includes(node.type.name)) targets.set(pos, node)
        }
        const { from, to, $from } = state.selection
        state.doc.nodesBetween(from, to, (node, pos) => {
            if (! node.isTextblock) return true
            add(node, pos)
            return false
        })
        if (! targets.size && $from.parent.isTextblock && $from.depth > 0) add($from.parent, $from.before())

        return [...targets].map(([pos, node]) => ({ pos, node }))
    },

    /** Змінює атрибут блоків-цілей одним кроком історії (Ctrl+Z повертає все разом). */
    updateTargets(editor, targets, attribute, value) {
        if (! targets.length) return false
        const tr = editor.state.tr
        targets.forEach(({ pos, node }) => tr.setNodeMarkup(pos, undefined, { ...node.attrs, [attribute]: value(node) }))
        editor.view.dispatch(tr)
        editor.view.focus()

        return true
    },

    /** Кнопки «Щільніше» / «Просторіше»; повторне натискання повертає звичайний інтервал. */
    setBlockSpacing(editor, spacing) {
        const targets = this.spacingTargets(editor, 'spacing')
        const reset = targets.length && targets.every(({ node }) => node.attrs.spacing === spacing)

        return this.updateTargets(editor, targets, 'spacing', () => (reset ? null : spacing))
    },

    blockSpacingIs(editor, spacing) {
        const targets = this.spacingTargets(editor, 'spacing')

        return targets.length > 0 && targets.every(({ node }) => node.attrs.spacing === spacing)
    },

    /** Кнопки відступу ліворуч: рівні 1–3, на телефоні крок менший (.prose-site [data-indent]). */
    changeIndent(editor, delta) {
        return this.updateTargets(editor, this.spacingTargets(editor, 'indent'), 'indent', (node) => {
            const level = Math.min(MAX_INDENT, Math.max(0, (Number(node.attrs.indent) || 0) + delta))

            return level ? String(level) : null
        })
    },

    /**
     * «Виправити вставлений текст» (html-paste-fix.js) для блоків верхнього рівня, яких торкається
     * виділення, або для всього тексту, якщо нічого не виділено. Один крок історії.
     */
    fixPastedText(editor) {
        const state = editor?.state
        if (! state) return false
        const { doc, selection } = state
        const start = selection.empty ? 0 : selection.$from.index(0)
        // Виділення, що закінчується на самому початку наступного блоку, цей блок не зачіпає
        const $to = selection.$to
        let atBlockStart = $to.depth > 0 && $to.parentOffset === 0 && $to.index(0) > start
        for (let depth = 1; atBlockStart && depth < $to.depth; depth++) atBlockStart = $to.index(depth) === 0
        const end = selection.empty ? doc.childCount : Math.max(start + 1, atBlockStart ? $to.index(0) : $to.indexAfter(0))

        const blocks = []
        let from = 0
        for (let index = 0; index < start; index++) from += doc.child(index).nodeSize
        let to = from
        for (let index = start; index < end; index++) {
            blocks.push(doc.child(index).toJSON())
            to += doc.child(index).nodeSize
        }

        const { blocks: fixed, changed } = fixPastedBlocks(blocks)
        if (! changed) {
            notify('Виправляти нічого: переносів рядків, порожніх абзаців і рядків із «•» не знайдено')
            return false
        }

        try {
            const nodes = fixed.map((json) => state.schema.nodeFromJSON(json))
            editor.view.dispatch(state.tr.replaceWith(from, to, nodes).scrollIntoView())
        } catch (error) {
            console.error(error)
            notify('Не вдалося виправити цей фрагмент — виділіть менший уривок тексту', 'danger')
            return false
        }
        editor.view.focus()
        notify('Текст виправлено. Скасувати — Ctrl+Z', 'success')

        return true
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
