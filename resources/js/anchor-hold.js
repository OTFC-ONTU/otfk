// Перехід до якоря (#vykladachi, розділи сторінок): поки вище догружаються зображення, iframe
// чи шрифти, ціль утримується на місці; утримання знімається після 3 с або щойно людина
// сама прокручує (колесо, дотик, клавіші). Відступ під шапку бере scroll-margin цілі.
// Перехід за посиланням на якір цієї ж сторінки замінює адресу (replaceState), а не додає
// запис в історію — кнопка «Назад» повертає на попередню сторінку.

const USER_EVENTS = ['wheel', 'touchstart', 'keydown', 'mousedown']

function target(hash) {
    if (! hash || hash.length < 2) return null
    try {
        return document.getElementById(decodeURIComponent(hash.slice(1)))
    } catch {
        return null
    }
}

export function holdAnchor(element, duration = 3000) {
    if (! element || typeof ResizeObserver === 'undefined') return

    let done = false
    const align = () => {
        if (! done) element.scrollIntoView({ block: 'start' })
    }
    const stop = () => {
        if (done) return
        done = true
        observer.disconnect()
        clearTimeout(timer)
        USER_EVENTS.forEach((type) => window.removeEventListener(type, stop, true))
    }
    const observer = new ResizeObserver(align)
    observer.observe(document.body)
    const timer = setTimeout(stop, duration)
    USER_EVENTS.forEach((type) => window.addEventListener(type, stop, { capture: true, passive: true }))
}

export function initAnchorHold() {
    // Після першого кадру: браузер уже сам прокрутив до цілі
    if (location.hash) requestAnimationFrame(() => holdAnchor(target(location.hash)))

    document.addEventListener('click', (event) => {
        const link = event.target.closest?.('a[href*="#"]')
        if (! link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey) return
        const url = new URL(link.href, location.href)
        if (url.origin !== location.origin || url.pathname !== location.pathname || url.search !== location.search) return
        const element = target(url.hash)
        if (! element) return
        // Перехід усередині сторінки не додає запис в історію: «Назад» веде на попередню сторінку,
        // а не нагору цієї; адреса з якорем оновлюється (нею можна поділитися)
        event.preventDefault()
        history.replaceState(history.state, '', url.hash)
        element.scrollIntoView({ block: 'start' })
        requestAnimationFrame(() => holdAnchor(element))
    })
}
