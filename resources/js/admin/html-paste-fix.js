// «Виправити вставлений текст» візуального режиму редактора адмінки (App\Filament\Forms\Components\HtmlRichEditor).
// Текст, вставлений з Word, Telegram чи листа, часто приходить одним абзацом із переносами рядків (<br>)
// і «списками» з символу «•» на початку рядка: на сайті він виглядає злитим, бо відступи мають лише
// справжні абзаци й списки. Тут — чиста функція над JSON-документом TipTap (без DOM і ProseMirror),
// застосування до редактора — fixPastedText() у html-editor.js.

/** Маркери, з якими рядок однозначно є пунктом списку (навіть один такий рядок). */
const BULLET = /^[\s ]*[•·▪▫◦●○■□►▸➢➤✓✔][\s ]*/

/** Тире й зірочка — пункт списку лише в серії з кількох рядків (одиночне «– …» буває прямою мовою). */
const DASH = /^[\s ]*[-–—*][\s ]+/

/** Нумерація «1.» / «1)» — теж лише серією. */
const NUMBER = /^[\s ]*(\d{1,3})[.)][\s ]+/

const WHITESPACE = /^[\s ]*$/

/** Вузли, усередині яких лежать блоки (абзаци, списки), — їх обходимо рекурсивно. */
const CONTAINERS = new Set(['doc', 'blockquote', 'listItem', 'details', 'detailsContent', 'tableCell', 'tableHeader', 'table', 'tableRow', 'bulletList', 'orderedList'])

const textOf = (node) => (node.content ?? []).map((child) => (child.type === 'text' ? child.text : '')).join('')

const isEmptyParagraph = (node) => node.type === 'paragraph'
    && (node.content ?? []).every((child) => child.type === 'text' && WHITESPACE.test(child.text) || child.type === 'hardBreak')

const hasLink = (node) => (node.content ?? []).some((child) => (child.marks ?? []).some((mark) => mark.type === 'link'))

/** Прибирає пробіли на краях рядка (зокрема нерозривні), зберігаючи позначки тексту. */
function trimInline(content) {
    const nodes = content.map((node) => ({ ...node }))
    const first = nodes.findIndex((node) => node.type !== 'text' || ! WHITESPACE.test(node.text))
    if (first === -1) return []
    let last = nodes.length - 1
    while (nodes[last].type === 'text' && WHITESPACE.test(nodes[last].text)) last--
    const trimmed = nodes.slice(first, last + 1)
    if (trimmed[0].type === 'text') trimmed[0].text = trimmed[0].text.replace(/^[\s ]+/, '')
    const end = trimmed[trimmed.length - 1]
    if (end.type === 'text') end.text = end.text.replace(/[\s ]+$/, '')

    return trimmed.filter((node) => node.type !== 'text' || node.text !== '')
}

/** Абзац із переносами рядків → окремі абзаци з тими самими атрибутами (вирівнювання, інтервал). */
function splitLines(node) {
    if (node.type !== 'paragraph' || ! (node.content ?? []).some((child) => child.type === 'hardBreak')) return [node]
    const lines = [[]]
    for (const child of node.content) {
        if (child.type === 'hardBreak') lines.push([])
        else lines[lines.length - 1].push(child)
    }

    return lines.map((line) => ({ ...node, content: trimInline(line) }))
}

/** Знімає маркер пункту з початку першого текстового вузла рядка. */
function stripMarker(paragraph, pattern) {
    const content = [...(paragraph.content ?? [])]
    const index = content.findIndex((child) => child.type === 'text')
    if (index !== -1) content[index] = { ...content[index], text: content[index].text.replace(pattern, '') }

    return { ...paragraph, content: trimInline(content) }
}

/** Пункт списку без вирівнювання рядка: «по ширині» у вузькому пункті на телефоні розтягує слова. */
function listItem(paragraph, pattern) {
    const { textAlign, ...attrs } = paragraph.attrs ?? {}
    const item = stripMarker(paragraph, pattern)
    if (Object.keys(attrs).length) item.attrs = attrs
    else delete item.attrs

    return { type: 'listItem', content: [item] }
}

/** Вид рядка-пункту: символ «•», тире чи номер; null — звичайний абзац. */
function markerOf(node) {
    if (node.type !== 'paragraph') return null
    const text = textOf(node)
    if (BULLET.test(text) && ! WHITESPACE.test(text.replace(BULLET, ''))) return { kind: 'bullet', pattern: BULLET }
    if (DASH.test(text)) return { kind: 'dash', pattern: DASH }
    const number = NUMBER.exec(text)
    if (number) return { kind: 'number', pattern: NUMBER, value: Number(number[1]) }

    return null
}

/** Серії рядків-пунктів → маркований або нумерований список. */
function buildLists(nodes) {
    const out = []
    for (let i = 0; i < nodes.length;) {
        const marker = markerOf(nodes[i])
        if (! marker) {
            out.push(nodes[i++])
            continue
        }

        const run = [nodes[i]]
        let j = i + 1
        while (j < nodes.length) {
            const next = markerOf(nodes[j])
            if (! next || next.kind !== marker.kind) break
            // Нумерація має йти підряд (1, 2, 3…), інакше це не список, а, скажімо, роки чи пункти документа
            if (marker.kind === 'number' && next.value !== marker.value + run.length) break
            run.push(nodes[j++])
        }

        const numbered = marker.kind === 'number'
        // Посилання з ручною нумерацією («1) Наказ.pdf») на сайті стають картками файлів, де номер у назві — потрібний
        if ((marker.kind !== 'bullet' && run.length < 2) || (numbered && run.some(hasLink))) {
            out.push(...run)
            i = j
            continue
        }

        out.push({
            type: numbered ? 'orderedList' : 'bulletList',
            ...(numbered ? { attrs: { start: marker.value } } : {}),
            content: run.map((paragraph) => listItem(paragraph, marker.pattern)),
        })
        i = j
    }

    return out
}

function fixBlocks(nodes, parentType) {
    let blocks = nodes.flatMap((node) => {
        if (CONTAINERS.has(node.type) && Array.isArray(node.content)) {
            return [{ ...node, content: fixBlocks(node.content, node.type) }]
        }

        return splitLines(node)
    })

    // Порожні абзаци-«відступи» не потрібні: відстань між абзацами на сайті задає оформлення
    const filled = blocks.filter((node) => ! isEmptyParagraph(node))
    blocks = filled.length ? filled : blocks.slice(0, 1)

    // Усередині пункту списку чи таблиці «•» не перетворюємо на вкладений список
    return ['listItem', 'tableCell', 'tableHeader', 'table', 'tableRow', 'bulletList', 'orderedList'].includes(parentType) ? blocks : buildLists(blocks)
}

/**
 * Виправляє вставлений текст у блоках документа TipTap: переноси рядків усередині абзацу → окремі абзаци,
 * рядки з «•» (або серії з «–»/«-»/«*» чи «1.», «2.»…) → справжні списки, порожні абзаци прибираються.
 *
 * @param {Array<object>} blocks  вузли верхнього рівня (doc.content або їх виділена частина)
 * @returns {{ blocks: Array<object>, changed: boolean }}
 */
export function fixPastedBlocks(blocks) {
    const fixed = fixBlocks(blocks ?? [], 'doc')

    return { blocks: fixed, changed: JSON.stringify(fixed) !== JSON.stringify(blocks ?? []) }
}
