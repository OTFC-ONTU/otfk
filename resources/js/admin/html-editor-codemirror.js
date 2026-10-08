// CodeMirror 6 для режиму «HTML»: підсвітка синтаксису, номери рядків, парні теги, пошук (Ctrl/Cmd+F),
// перенесення довгих рядків. Тема стежить за світлою/темною темою адмінки Filament.
import { basicSetup } from 'codemirror'
import { Annotation, Compartment, EditorState } from '@codemirror/state'
import { EditorView, keymap } from '@codemirror/view'
import { indentWithTab } from '@codemirror/commands'
import { html } from '@codemirror/lang-html'
import { oneDark } from '@codemirror/theme-one-dark'

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
                theme.of(isDark() ? oneDark : []),
                EditorView.updateListener.of((update) => {
                    if (update.docChanged && ! update.transactions.some((tr) => tr.annotation(external))) {
                        onChange(update.state.doc.toString())
                    }
                }),
            ],
        }),
    })

    const observer = new MutationObserver(() => {
        view.dispatch({ effects: theme.reconfigure(isDark() ? oneDark : []) })
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
        focus() {
            view.focus()
        },
        destroy() {
            observer.disconnect()
            view.destroy()
        },
    }
}
