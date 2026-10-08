// Вузол TipTap «embed» для HtmlRichEditor: <iframe> (PDF, відео YouTube, документ Google, карта)
// зберігається з усіма атрибутами (пара — App\Filament\Forms\Components\RichEditor\EmbedExtension).
// Завантажується Filament як ES-модуль (RichContentPlugin::getTipTapJsExtensions()) і бере TipTap
// з window.FilamentRichEditor, щоб ділити один екземпляр ProseMirror із редактором.
// У редакторі iframe не вантажиться — показується картка з типом, назвою та адресою.

const ATTRIBUTES = ['src', 'title', 'width', 'height', 'class', 'style', 'loading', 'allow', 'allowfullscreen', 'frameborder', 'referrerpolicy']

const kindOf = (src) => {
    if (/(?:youtube(?:-nocookie)?\.com|youtu\.be)\//i.test(src)) return ['Відео YouTube', '▶']
    if (/(?:google\.com\/maps|maps\.google\.com)/i.test(src)) return ['Карта Google', '⌖']
    if (/(?:docs|drive)\.google\.com\//i.test(src)) return ['Документ Google', '▤']
    if (/\.pdf(?:$|[?#])/i.test(src)) return ['PDF', '▤']

    return ['Вбудований вміст', '▢']
}

const fileName = (src) => {
    try {
        return decodeURIComponent(new URL(src, location.href).pathname.split('/').pop() || src)
    } catch {
        return src
    }
}

function render(dom, node) {
    const src = node.attrs.src ?? ''
    const [label, mark] = kindOf(src)
    dom.replaceChildren()

    const icon = document.createElement('span')
    icon.textContent = mark
    icon.setAttribute('aria-hidden', 'true')
    icon.style.cssText = 'flex:none;display:grid;place-items:center;width:2.5rem;height:2.5rem;border-radius:.5rem;background:rgb(37 99 235 / .1);color:rgb(37 99 235);font-size:1.1rem'

    const text = document.createElement('span')
    text.style.cssText = 'min-width:0;display:grid;gap:.125rem'
    const title = document.createElement('strong')
    title.textContent = `${label}: ${node.attrs.title || fileName(src)}`
    title.style.cssText = 'font-size:.875rem;line-height:1.3'
    const url = document.createElement('span')
    url.textContent = src
    url.style.cssText = 'font-size:.75rem;opacity:.65;overflow:hidden;text-overflow:ellipsis;white-space:nowrap'
    text.append(title, url)

    dom.append(icon, text)
}

export default function () {
    const { Node } = window.FilamentRichEditor.tiptap.core

    return Node.create({
        name: 'embed',
        group: 'block',
        atom: true,
        selectable: true,
        draggable: true,

        addAttributes() {
            return Object.fromEntries(ATTRIBUTES.map((name) => [name, {
                default: null,
                // Булевий атрибут без значення (allowfullscreen) інакше зник би при виводі
                parseHTML: (element) => (element.hasAttribute(name) ? (element.getAttribute(name) || name) : null),
                renderHTML: (attributes) => (attributes[name] == null ? {} : { [name]: attributes[name] }),
            }]))
        },

        parseHTML() {
            return [{ tag: 'iframe' }]
        },

        renderHTML({ HTMLAttributes }) {
            return ['iframe', HTMLAttributes]
        },

        addNodeView() {
            return ({ node }) => {
                const dom = document.createElement('div')
                dom.className = 'otfk-embed-card'
                dom.contentEditable = 'false'
                dom.title = 'Виділіть і натисніть «PDF / відео», щоб замінити; Delete — видалити'
                dom.style.cssText = 'display:flex;align-items:center;gap:.75rem;margin:.75rem 0;padding:.625rem .75rem;border-radius:.75rem;border:1px solid rgb(148 163 184 / .5);cursor:grab'
                render(dom, node)

                return {
                    dom,
                    update(updated) {
                        if (updated.type.name !== 'embed') return false
                        render(dom, updated)

                        return true
                    },
                    selectNode() {
                        dom.style.boxShadow = '0 0 0 2px rgb(37 99 235)'
                    },
                    deselectNode() {
                        dom.style.boxShadow = ''
                    },
                }
            }
        },
    })
}
