import { test } from 'node:test';
import assert from 'node:assert/strict';
import { detailsSnippet, headingsToDetails } from '../../resources/js/admin/html-sections.js';

test('розділи найвищого рівня заголовків стають розгортними блоками, вступ лишається', () => {
    const { html, count } = headingsToDetails(
        '<p>Вступ</p><h4>Підручники</h4><p>Один</p><ul><li>10 клас</li></ul><h4>Інформація</h4><p>Два</p>',
    );
    assert.equal(count, 2);
    assert.equal(html,
        '<p>Вступ</p>'
        + '<details><summary>Підручники</summary><p>Один</p><ul><li>10 клас</li></ul></details>'
        + '<details><summary>Інформація</summary><p>Два</p></details>');
});

test('нижчі заголовки потрапляють усередину блоку старшого рівня', () => {
    const { html, count } = headingsToDetails('<h2>А</h2><h3>А.1</h3><p>x</p><h2>Б</h2><p>y</p>');
    assert.equal(count, 2);
    assert.equal(html, '<details><summary>А</summary><h3>А.1</h3><p>x</p></details><details><summary>Б</summary><p>y</p></details>');
});

test('наявні details, заголовки без вмісту та порожні заголовки не змінюються', () => {
    const source = '<details><summary>Є</summary><h4>Всередині</h4><p>z</p></details><h4>Порожній</h4><h4> </h4>';
    assert.deepEqual(headingsToDetails(source), { html: source, count: 0 });
});

test('вкладені однойменні теги та void-елементи не ламають межі розділів', () => {
    const { html } = headingsToDetails('<h3 id="a">Фото <em>2024</em></h3><div><div><img src="/a.jpg"><br></div></div><p>t</p><h3>Далі</h3><p>u</p>');
    assert.equal(html,
        '<details><summary>Фото <em>2024</em></summary><div><div><img src="/a.jpg"><br></div></div><p>t</p></details>'
        + '<details><summary>Далі</summary><p>u</p></details>');
});

test('вставка блоку: шаблон або виділення із заголовком як підписом', () => {
    assert.equal(detailsSnippet(''), '<details><summary>Заголовок блоку</summary><p>Текст блоку</p></details>');
    assert.equal(detailsSnippet('<h4>Заходи</h4>\n<p>Текст</p>'), '<details><summary>Заходи</summary><p>Текст</p></details>');
    assert.equal(detailsSnippet('<p>Лише текст</p>'), '<details><summary>Заголовок блоку</summary><p>Лише текст</p></details>');
});
