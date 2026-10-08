import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
    STORAGE_KEY, pageLocation, pageTitle, referrer, classifyLink,
    readConsent, writeConsent, analyticsCookieNames, isTrackingAllowed,
} from '../../resources/js/analytics.js';

const KEYS = ['page', 'category', 'year'];
const BASE = 'https://otfk.od.ua/novyny';

test('page_location зберігає лише page/category/year і відкидає фрагмент', () => {
    assert.equal(
        pageLocation('https://otfk.od.ua/novyny?utm_source=x&page=2&q=Іван&category=sport&fbclid=1#top', KEYS),
        'https://otfk.od.ua/novyny?page=2&category=sport',
    );
    assert.equal(pageLocation('https://otfk.od.ua/poshuk?q=email%40example.com', KEYS), 'https://otfk.od.ua/poshuk');
    assert.equal(pageLocation('not a url', KEYS), '');
});

test('заголовок не містить пошукового запиту', () => {
    assert.equal(
        pageTitle('Пошук: «Петренко» — ОТФК ОНТУ', 'https://otfk.od.ua/poshuk?q=Петренко', KEYS),
        'Пошук: «…» — ОТФК ОНТУ',
    );
    assert.equal(pageTitle('Новини — ОТФК', 'https://otfk.od.ua/novyny?page=2', KEYS), 'Новини — ОТФК');
});

test('реферер: свій — без зайвих параметрів, сторонній — лише origin і шлях', () => {
    assert.equal(referrer('https://otfk.od.ua/poshuk?q=x&page=3', 'https://otfk.od.ua', KEYS), 'https://otfk.od.ua/poshuk?page=3');
    assert.equal(referrer('https://www.google.com/search?q=otfk', 'https://otfk.od.ua', KEYS), 'https://www.google.com/search');
    assert.equal(referrer('', 'https://otfk.od.ua', KEYS), '');
});

test('класифікація посилань без персональних даних', () => {
    assert.deepEqual(classifyLink('tel:+380482000000', BASE), { name: 'click_phone', params: {} });
    assert.deepEqual(classifyLink('mailto:priyom@otfk.od.ua?subject=Hi', BASE), { name: 'click_email', params: {} });
    assert.deepEqual(classifyLink('/storage/documents/Polozhennya.PDF?v=2#p3', BASE), {
        name: 'file_download',
        params: { file_extension: 'pdf', link_url: 'https://otfk.od.ua/storage/documents/Polozhennya.PDF' },
    });
    assert.deepEqual(classifyLink('/storage/mirror/archive', BASE), {
        name: 'file_download',
        params: { file_extension: '', link_url: 'https://otfk.od.ua/storage/mirror/archive' },
    });
    assert.equal(classifyLink('https://docs.example.com/plan.xlsx', BASE).params.file_extension, 'xlsx');
    assert.equal(classifyLink('https://example.com/storage/x', BASE), null);
    assert.equal(classifyLink('/novyny/test', BASE), null);
    assert.equal(classifyLink('/specialnist.html', BASE), null);
    assert.equal(classifyLink('javascript:alert(1)', BASE), null);
});

test('вибір зберігається з версією; стара версія чи помилка сховища — немає вибору', () => {
    const map = new Map();
    const storage = { getItem: k => map.get(k) ?? null, setItem: (k, v) => map.set(k, v) };
    assert.equal(readConsent(storage, 1), null);
    writeConsent(storage, 1, 'denied');
    assert.equal(readConsent(storage, 1), 'denied');
    assert.equal(readConsent(storage, 2), null);
    assert.equal(JSON.parse(map.get(STORAGE_KEY)).choice, 'denied');
    map.set(STORAGE_KEY, '{broken');
    assert.equal(readConsent(storage, 1), null);

    const broken = { getItem() { throw new Error('blocked'); }, setItem() { throw new Error('blocked'); } };
    assert.equal(readConsent(broken, 1), null);
    assert.doesNotThrow(() => writeConsent(broken, 1, 'granted'));
    assert.equal(readConsent(null, 1), null);
});

test('cookies GA для видалення', () => {
    assert.deepEqual(analyticsCookieNames('_ga=1; XSRF-TOKEN=x; _ga_ABC123=2; _gid=3; laravel_session=y'), ['_ga', '_ga_ABC123', '_gid']);
});

test('тег дозволений лише з коректним ID і без позначки неосновного домену', () => {
    assert.equal(isTrackingAllowed({ ga4Id: 'G-ABC12345' }), true);
    assert.equal(isTrackingAllowed({ analyticsDisabled: '' }), false);
    assert.equal(isTrackingAllowed({ ga4Id: 'G-ABC12345', analyticsDisabled: '' }), false);
    assert.equal(isTrackingAllowed({ ga4Id: 'UA-1-1' }), false);
    assert.equal(isTrackingAllowed({}), false);
});
