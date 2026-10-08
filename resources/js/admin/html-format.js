// Форматування HTML для режиму «HTML» редактора адмінки (App\Filament\Forms\Components\HtmlRichEditor).
//
// Переноси й відступи додаються лише навколо блокових тегів (p, li, tr, td, details…), де пробіли
// на відображення не впливають; текст, інлайн-теги та вміст pre/textarea/script/style лишаються
// без змін. Блок, що містить лише інлайн-вміст, пишеться одним рядком: <p>Текст <b>жирний</b></p>.

const BLOCK = new Set([
    'address', 'article', 'aside', 'blockquote', 'body', 'caption', 'col', 'colgroup', 'dd', 'details',
    'dialog', 'div', 'dl', 'dt', 'fieldset', 'figcaption', 'figure', 'footer', 'form', 'h1', 'h2', 'h3',
    'h4', 'h5', 'h6', 'header', 'hgroup', 'hr', 'li', 'main', 'nav', 'ol', 'p', 'section', 'summary',
    'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'tr', 'ul',
])

const VOID = new Set(['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'source', 'track', 'wbr'])

const RAW = new Set(['pre', 'textarea', 'script', 'style'])

const TOKEN = /<!--[\s\S]*?-->|<\/?[a-zA-Z][\w:-]*(?:"[^"]*"|'[^']*'|[^'">])*>|[^<]+|</g

export function formatHtml(source, indentUnit = '  ') {
    const html = String(source ?? '')
    const tokens = html.match(TOKEN) ?? []
    let out = ''
    // Стек відкритих блоків: чи мав блок дочірні блоки (тоді закривальний тег — з нового рядка)
    const stack = []
    let atLineStart = true
    // Останній виведений токен — блоковий тег або сирий блок (коментар після нього — з нового рядка)
    let afterBlock = true

    const newline = () => {
        out = out.replace(/[ \t\r\n]+$/, '')
        if (out !== '') {
            out += '\n' + indentUnit.repeat(stack.length)
        }
        atLineStart = true
    }

    for (let i = 0; i < tokens.length; i++) {
        const token = tokens[i]
        const tag = /^<(\/?)([a-zA-Z][\w:-]*)/.exec(token)
        const name = tag?.[2].toLowerCase()

        // Сирий вміст — до відповідного закривального тегу без змін
        if (tag && ! tag[1] && RAW.has(name)) {
            if (BLOCK.has(name) || name === 'pre') {
                if (stack.length) stack[stack.length - 1].hasBlock = true
                newline()
            }
            let raw = token
            const close = new RegExp(`^</${name}\\s*>$`, 'i')
            while (++i < tokens.length) {
                raw += tokens[i]
                if (close.test(tokens[i])) break
            }
            out += raw
            atLineStart = false
            afterBlock = BLOCK.has(name) || name === 'pre'
            continue
        }

        if (tag && BLOCK.has(name)) {
            if (tag[1]) {
                // Закривальний блоковий тег
                const index = stack.map((item) => item.name).lastIndexOf(name)
                if (index === -1) {
                    out += token
                    afterBlock = false
                    continue
                }
                const [opened] = stack.splice(index)
                if (opened.hasBlock) {
                    newline()
                } else {
                    out = out.replace(/[ \t\r\n]+$/, '')
                }
                out += token
                atLineStart = false
                afterBlock = true
                continue
            }

            if (stack.length) stack[stack.length - 1].hasBlock = true
            newline()
            out += token
            atLineStart = false
            afterBlock = true
            if (! VOID.has(name) && ! /\/>$/.test(token)) {
                stack.push({ name, hasBlock: false })
            }
            continue
        }

        if (token.startsWith('<!--') && (afterBlock || out === '')) {
            if (stack.length) stack[stack.length - 1].hasBlock = true
            newline()
            out += token
            atLineStart = false
            afterBlock = true
            continue
        }

        if (! tag && ! token.startsWith('<')) {
            // Текст: пробіли на межі блоку не відображаються — прибираємо лише їх
            const text = atLineStart ? token.replace(/^[ \t\r\n]+/, '') : token
            if (text === '') continue
            out += text
            atLineStart = false
            afterBlock = false
            continue
        }

        out += token
        atLineStart = false
        afterBlock = false

        if (name === 'br' && ! tag[1]) {
            // Після <br> — новий рядок у тому ж відступі (пробіл на початку рядка не відображається)
            const next = tokens[i + 1]
            if (next && ! /^\s*$/.test(next) && ! /^<\/?(?:br)\b/i.test(next)) {
                out += '\n' + indentUnit.repeat(stack.length)
                atLineStart = true
            }
        }
    }

    return out.replace(/[ \t\r\n]+$/, '') + (out.trim() === '' ? '' : '\n')
}
