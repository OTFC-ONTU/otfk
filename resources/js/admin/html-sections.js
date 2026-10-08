// Розгортні блоки (<details><summary>) для режиму «HTML» редактора адмінки
// (App\Filament\Forms\Components\HtmlRichEditor). На сайті всі native <details> у .prose-site
// оформлені єдиним акордеоном, тому блоки пишуться без класів і inline-стилів.

const TOKEN = /<!--[\s\S]*?-->|<\/?[a-zA-Z][\w:-]*(?:"[^"]*"|'[^']*'|[^'">])*>|[^<]+|</g

const VOID = new Set(['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'source', 'track', 'wbr'])

const HEADING = /^<h([2-6])\b[^>]*>([\s\S]*)<\/h\1\s*>$/i

/** Розбиває HTML на вузли верхнього рівня (елементи з усім вмістом, текст, коментарі). */
function topLevelNodes(html) {
    const nodes = []
    let current = ''
    let depth = 0

    for (const token of String(html ?? '').match(TOKEN) ?? []) {
        const tag = /^<(\/?)([a-zA-Z][\w:-]*)/.exec(token)
        if (depth === 0 && current !== '') {
            nodes.push(current)
            current = ''
        }
        current += token
        if (tag && ! tag[1] && ! VOID.has(tag[2].toLowerCase()) && ! /\/>$/.test(token)) {
            depth++
        } else if (tag && tag[1]) {
            depth = Math.max(0, depth - 1)
        }
    }
    if (current !== '') nodes.push(current)

    return nodes
}

const headingOf = (node) => {
    const match = HEADING.exec(node.trim())
    if (! match) return null
    const summary = match[2].replace(/<\/?(?:p|div|br)\b[^>]*>/gi, ' ').replace(/\s+/g, ' ').trim()

    return summary.replace(/<[^>]*>/g, '').trim() === '' ? null : { level: Number(match[1]), summary }
}

const details = (summary, body) => `<details><summary>${summary}</summary>${body}</details>`

/**
 * Перетворює розділи «заголовок + вміст до наступного такого ж заголовка» на розгортні блоки.
 * Береться найвищий рівень заголовків верхнього рівня (h2…h6); текст до першого заголовка,
 * заголовки без вмісту й уже наявні <details> лишаються без змін.
 *
 * @returns {{ html: string, count: number }}
 */
export function headingsToDetails(html) {
    const nodes = topLevelNodes(html)
    const levels = nodes.map(headingOf).filter(Boolean).map((heading) => heading.level)
    if (! levels.length) return { html: String(html ?? ''), count: 0 }
    const level = Math.min(...levels)

    let out = ''
    let count = 0
    for (let i = 0; i < nodes.length; i++) {
        const heading = headingOf(nodes[i])
        if (! heading || heading.level !== level) {
            out += nodes[i]
            continue
        }
        const start = i
        let body = ''
        while (i + 1 < nodes.length) {
            // Межа розділу — будь-який заголовок того ж або старшого рівня, навіть порожній
            const next = HEADING.exec(nodes[i + 1].trim())
            if (next && Number(next[1]) <= level) break
            body += nodes[++i]
        }
        if (body.trim() === '') {
            out += nodes[start] + body
            continue
        }
        out += details(heading.summary, body.trim())
        count++
    }

    return { html: out, count }
}

/**
 * Розгортний блок для вставки: виділений HTML стає вмістом блоку, а заголовок на його
 * початку — підписом блоку.
 */
export function detailsSnippet(selection = '') {
    const nodes = topLevelNodes(selection).filter((node) => node.trim() !== '')
    if (! nodes.length) return details('Заголовок блоку', '<p>Текст блоку</p>')
    const heading = headingOf(nodes[0])
    const body = (heading ? nodes.slice(1) : nodes).join('').trim()

    return details(heading?.summary ?? 'Заголовок блоку', body || '<p>Текст блоку</p>')
}
