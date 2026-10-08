// Google Analytics 4 з консервативною згодою (App\Support\Analytics,
// docs/seo-plan.md «Аналітика»). Чанк вантажиться лише коли сервер вивів
// #analytics-consent. До натискання «Прийняти» тег Google не завантажується й
// жодних запитів до аналітики не надсилається. Вибір зберігається в
// localStorage з версією тексту згоди; «Налаштування cookies» у підвалі знову
// показує банер. Події: click_phone, click_email, file_download — без
// персональних даних і довільних query-параметрів. У потоці GA4 «Розширені
// вимірювання → Завантаження файлів» має бути вимкнено, інакше file_download
// рахуватиметься двічі.

export const STORAGE_KEY = 'otfk-analytics-consent';
export const ID_PATTERN = /^G-[A-Z0-9]{4,20}$/;
export const FILE_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp', 'zip'];

/** Адреса сторінки лише з дозволеними параметрами (page, category, year) і без #фрагмента. */
export function pageLocation(href, allowedKeys) {
    let url;
    try { url = new URL(href); } catch { return ''; }
    const kept = new URLSearchParams();
    for (const key of allowedKeys) {
        const value = url.searchParams.get(key);
        if (value !== null && value !== '') kept.set(key, value);
    }
    const query = kept.toString();
    return url.origin + url.pathname + (query ? '?' + query : '');
}

/**
 * Заголовок без значень відкинутих параметрів: сторінка пошуку містить запит
 * у <title>, і він не має потрапити в аналітику.
 */
export function pageTitle(title, href, allowedKeys) {
    let url;
    try { url = new URL(href); } catch { return title; }
    let safe = String(title);
    for (const [key, value] of url.searchParams) {
        if (allowedKeys.includes(key) || value.trim() === '') continue;
        safe = safe.split(value.trim()).join('…');
    }
    return safe;
}

/** Реферер: свій сайт — як page_location, сторонній — лише origin і шлях. */
export function referrer(ref, origin, allowedKeys) {
    if (!ref) return '';
    let url;
    try { url = new URL(ref); } catch { return ''; }
    if (url.protocol !== 'http:' && url.protocol !== 'https:') return '';
    return url.origin === origin ? pageLocation(ref, allowedKeys) : url.origin + url.pathname;
}

/**
 * Подія для кліку за посиланням або null. Номер телефону/адресу пошти не
 * передаємо; для файлу — розширення й адреса без query/фрагмента.
 */
export function classifyLink(href, base) {
    let url;
    try { url = new URL(href, base); } catch { return null; }
    if (url.protocol === 'tel:') return { name: 'click_phone', params: {} };
    if (url.protocol === 'mailto:') return { name: 'click_email', params: {} };
    if (url.protocol !== 'http:' && url.protocol !== 'https:') return null;

    const path = url.pathname;
    const match = /\.([a-z0-9]{1,5})$/i.exec(path);
    const extension = match ? match[1].toLowerCase() : '';
    const sameOrigin = url.origin === new URL(base).origin;
    const isStorage = sameOrigin && path.startsWith('/storage/');
    if (!isStorage && !FILE_EXTENSIONS.includes(extension)) return null;

    return {
        name: 'file_download',
        params: { file_extension: extension, link_url: url.origin + path },
    };
}

/** Чи можна завантажувати gtag: коректний ID і немає позначки неосновного домену. */
export function isTrackingAllowed(dataset) {
    return ID_PATTERN.test(String(dataset.ga4Id || '')) && !('analyticsDisabled' in dataset);
}

/** Збережений вибір поточної версії: 'granted' | 'denied' | null. */
export function readConsent(storage, version) {
    try {
        const saved = JSON.parse(storage.getItem(STORAGE_KEY) || 'null');
        if (saved && saved.v === version && (saved.choice === 'granted' || saved.choice === 'denied')) {
            return saved.choice;
        }
    } catch { /* приватний режим, пошкоджене значення */ }
    return null;
}

export function writeConsent(storage, version, choice) {
    try {
        storage.setItem(STORAGE_KEY, JSON.stringify({ v: version, choice, at: new Date().toISOString().slice(0, 10) }));
    } catch { /* сховище недоступне: вибір діє до кінця сторінки */ }
}

/** Імена cookies Google Analytics (_ga, _ga_XXXX, _gid, _gat…). */
export function analyticsCookieNames(cookieString) {
    return String(cookieString || '').split(';')
        .map(part => part.split('=')[0].trim())
        .filter(name => /^_(ga|gid|gat)/.test(name));
}

/** Видалення cookies GA «наскільки можливо»: для поточного й батьківських доменів. */
function deleteAnalyticsCookies() {
    const parts = location.hostname.split('.');
    const domains = [''];
    for (let i = 0; i < parts.length - 1; i++) domains.push('; domain=.' + parts.slice(i).join('.'));
    for (const name of analyticsCookieNames(document.cookie)) {
        for (const domain of domains) {
            document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/' + domain;
        }
    }
}

export function initAnalytics(root) {
    const id = String(root.dataset.ga4Id || '');
    // Тег дозволений лише з коректним ID і без позначки data-analytics-disabled
    // (сервер віддає ID тільки основному домену). Інакше банер, вибір і
    // «Налаштування cookies» працюють так само, але gtag не завантажується ніколи.
    const tracking = isTrackingAllowed(root.dataset);
    const version = Number(root.dataset.consentVersion || 1);
    let allowedKeys = [];
    try { allowedKeys = JSON.parse(root.dataset.queryKeys || '[]'); } catch { /* лише шлях */ }

    let storage = null;
    try { storage = window.localStorage; } catch { /* заблоковано */ }
    const banner = root.querySelector('[data-consent-banner]');
    let granted = false;
    let loaded = false;

    function gtag() { window.dataLayer.push(arguments); }

    function load() {
        if (!tracking) return;
        window['ga-disable-' + id] = false;
        if (loaded) {
            gtag('consent', 'update', { analytics_storage: 'granted' });
            return;
        }
        loaded = true;
        window.dataLayer = window.dataLayer || [];
        gtag('consent', 'default', {
            analytics_storage: 'granted',
            ad_storage: 'denied',
            ad_user_data: 'denied',
            ad_personalization: 'denied',
        });
        gtag('js', new Date());
        gtag('config', id, {
            page_location: pageLocation(location.href, allowedKeys),
            page_title: pageTitle(document.title, location.href, allowedKeys),
            page_referrer: referrer(document.referrer, location.origin, allowedKeys),
            allow_google_signals: false,
            allow_ad_personalization_signals: false,
        });
        const script = document.createElement('script');
        script.async = true;
        script.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(id);
        document.head.appendChild(script);
    }

    function revoke() {
        if (tracking) window['ga-disable-' + id] = true;
        if (loaded) gtag('consent', 'update', { analytics_storage: 'denied' });
        deleteAnalyticsCookies();
    }

    function show() {
        if (!banner) return;
        banner.hidden = false;
        banner.querySelector('[data-consent-accept]')?.focus({ preventScroll: true });
    }

    function hide(returnFocus) {
        if (!banner) return;
        const hadFocus = banner.contains(document.activeElement);
        banner.hidden = true;
        if (hadFocus && returnFocus) returnFocus.focus({ preventScroll: true });
    }

    let opener = null;
    function choose(choice) {
        writeConsent(storage, version, choice);
        granted = choice === 'granted';
        granted ? load() : revoke();
        hide(opener);
        opener = null;
    }

    banner?.querySelector('[data-consent-accept]')?.addEventListener('click', () => choose('granted'));
    banner?.querySelector('[data-consent-reject]')?.addEventListener('click', () => choose('denied'));
    banner?.addEventListener('keydown', e => {
        // Escape закриває банер, відкритий з підвалу, без зміни попереднього вибору.
        if (e.key === 'Escape' && opener) { hide(opener); opener = null; }
    });

    document.querySelectorAll('[data-analytics-settings]').forEach(button => {
        button.hidden = false;
        button.addEventListener('click', () => { opener = button; show(); });
    });

    document.addEventListener('click', e => {
        if (!granted || !loaded) return;
        const link = e.target instanceof Element ? e.target.closest('a[href]') : null;
        if (!link) return;
        const event = classifyLink(link.getAttribute('href'), location.href);
        if (event) gtag('event', event.name, event.params);
    }, { capture: true, passive: true });

    const saved = readConsent(storage, version);
    if (saved === 'granted') {
        granted = true;
        load();
    } else if (saved === null) {
        // Без фокуса: банер не перехоплює клавіатуру при завантаженні сторінки.
        if (banner) banner.hidden = false;
    }
}
