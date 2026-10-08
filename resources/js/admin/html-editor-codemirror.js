// CodeMirror 6 для режиму «HTML»: підсвітка синтаксису, номери рядків, парні теги, пошук (Ctrl/Cmd+F),
// перенесення довгих рядків. Тема стежить за світлою/темною темою адмінки Filament.
import { basicSetup } from 'codemirror'
import { Annotation, Compartment, EditorState } from '@codemirror/state'
import { EditorView, keymap } from '@codemirror/view'
import { indentWithTab } from '@codemirror/commands'
import { html } from '@codemirror/lang-html'
import { HighlightStyle, syntaxHighlighting } from '@codemirror/language'
import { tags as t } from '@lezer/highlight'

// Заміна документа ззовні (перемикання режиму) — не правка користувача
const external = Annotation.define()

const isDark = () => document.documentElement.classList.contains('dark')

const base = EditorView.theme({
    '&': { fontSize: '12.5px', borderRadius: '0.5rem', overflow: 'hidden' },
    '.cm-scroller': {
        fontFamily: 'ui-monospace, SFMono-Regular, Menlo, Consolas, monospace',
        lineHeight: '1.6',
        minHeight: '20rem',
        maxHeight: '70vh',
    },
    '&.cm-focused': { outline: '2px solid rgb(var(--primary-600))' },
})

// Контрастні палітри (на основі GitHub light/dark): теги, атрибути й значення чітко різняться від тексту
const light = [
    EditorView.theme({
        '&': { backgroundColor: '#ffffff', color: '#1f2328' },
        '.cm-gutters': { backgroundColor: '#f6f8fa', color: '#6e7781', borderRight: '1px solid #d0d7de' },
        '.cm-activeLine': { backgroundColor: '#f0f6ff' },
        '.cm-activeLineGutter': { backgroundColor: '#e7effa', color: '#1f2328' },
        '&.cm-focused .cm-selectionBackground, .cm-selectionBackground, ::selection': { backgroundColor: '#b6d7ff !important' },
        '.cm-matchingBracket, &.cm-focused .cm-matchingBracket': { backgroundColor: '#ffe58f', color: 'inherit', outline: '1px solid #d4a72c' },
        '.cm-searchMatch': { backgroundColor: '#fff3b0', outline: '1px solid #d4a72c' },
    }),
    syntaxHighlighting(HighlightStyle.define([
        { tag: t.tagName, color: '#116329', fontWeight: '600' },
        { tag: t.angleBracket, color: '#6e7781' },
        { tag: t.attributeName, color: '#0550ae' },
        { tag: t.attributeValue, color: '#a40e26' },
        { tag: [t.comment, t.blockComment], color: '#6e7781', fontStyle: 'italic' },
        { tag: [t.character, t.processingInstruction], color: '#8250df' },
        { tag: t.invalid, color: '#cf222e', textDecoration: 'underline wavy' },
    ])),
]

const dark = [
    EditorView.theme({
        '&': { backgroundColor: '#0d1117', color: '#e6edf3' },
        '.cm-content': { caretColor: '#e6edf3' },
        '.cm-cursor, .cm-dropCursor': { borderLeftColor: '#e6edf3' },
        '.cm-gutters': { backgroundColor: '#161b22', color: '#8b949e', borderRight: '1px solid #30363d' },
        '.cm-activeLine': { backgroundColor: '#161b22' },
        '.cm-activeLineGutter': { backgroundColor: '#1f2630', color: '#e6edf3' },
        '&.cm-focused .cm-selectionBackground, .cm-selectionBackground, ::selection': { backgroundColor: '#264f78 !important' },
        '.cm-matchingBracket, &.cm-focused .cm-matchingBracket': { backgroundColor: '#3b5070', color: '#ffffff', outline: '1px solid #79c0ff' },
        '.cm-searchMatch': { backgroundColor: '#5a4a00', outline: '1px solid #e3b341' },
        '.cm-panels': { backgroundColor: '#161b22', color: '#e6edf3' },
        '.cm-tooltip': { backgroundColor: '#161b22', color: '#e6edf3', border: '1px solid #30363d' },
    }, { dark: true }),
    syntaxHighlighting(HighlightStyle.define([
        { tag: t.tagName, color: '#7ee787', fontWeight: '600' },
        { tag: t.angleBracket, color: '#8b949e' },
        { tag: t.attributeName, color: '#79c0ff' },
        { tag: t.attributeValue, color: '#ffa198' },
        { tag: [t.comment, t.blockComment], color: '#8b949e', fontStyle: 'italic' },
        { tag: [t.character, t.processingInstruction], color: '#d2a8ff' },
        { tag: t.invalid, color: '#ff7b72', textDecoration: 'underline wavy' },
    ], { themeType: 'dark' })),
]

export function createEditor(parent, { doc, label, onChange }) {
    const theme = new Compartment()

    const view = new EditorView({
        parent,
        state: EditorState.create({
            doc,
            extensions: [
                basicSetup,
                keymap.of([indentWithTab]),
                html({ autoCloseTags: true }),
                EditorView.lineWrapping,
                EditorView.contentAttributes.of({ 'aria-label': label, spellcheck: 'false' }),
                base,
                theme.of(isDark() ? dark : light),
                EditorView.updateListener.of((update) => {
                    if (update.docChanged && ! update.transactions.some((tr) => tr.annotation(external))) {
                        onChange(update.state.doc.toString())
                    }
                }),
            ],
        }),
    })

    const observer = new MutationObserver(() => {
        view.dispatch({ effects: theme.reconfigure(isDark() ? dark : light) })
    })
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] })

    return {
        setDoc(text) {
            if (text === view.state.doc.toString()) return
            view.dispatch({
                changes: { from: 0, to: view.state.doc.length, insert: text },
                annotations: external.of(true),
                selection: { anchor: 0 },
            })
            view.scrollDOM.scrollTop = 0
        },
        getDoc() {
            return view.state.doc.toString()
        },
        /** Замінює виділення результатом transform(виділений текст) — як правку користувача (з undo). */
        replaceSelection(transform) {
            const { from, to } = view.state.selection.main
            const insert = transform(view.state.sliceDoc(from, to))
            view.dispatch({ changes: { from, to, insert }, selection: { anchor: from, head: from + insert.length }, scrollIntoView: true })
            view.focus()
        },
        /** Замінює весь документ як правку користувача (з undo). */
        replaceDoc(text) {
            if (text === view.state.doc.toString()) return
            view.dispatch({ changes: { from: 0, to: view.state.doc.length, insert: text } })
            view.focus()
        },
        focus() {
            view.focus()
        },
        destroy() {
            observer.disconnect()
            view.destroy()
        },
    }
}
