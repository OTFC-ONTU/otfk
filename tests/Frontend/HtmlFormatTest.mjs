import { test } from 'node:test';
import assert from 'node:assert/strict';
import { formatHtml } from '../../resources/js/admin/html-format.js';

// Пробіли, які браузер не відображає: між блоками та на межах блоків
const visible = (html) => html
    .replace(/\s+/g, ' ')
    .replace(/\s*(<\/?(?:p|div|ul|ol|li|table|tbody|tr|td|th|details|summary|h[1-6]|blockquote|figure)\b[^>]*>)\s*/gi, '$1')
    .replace(/<br>\s*/gi, '<br>')
    .trim();

test('блоки — з нового рядка з відступом, листові блоки — одним рядком', () => {
    assert.equal(
        formatHtml('<h2>Заголовок</h2><p>Текст <strong>жирний</strong> і <a href="/novyny">посилання</a>.</p><ul><li>Один</li><li>Два</li></ul>'),
        '<h2>Заголовок</h2>\n'
        + '<p>Текст <strong>жирний</strong> і <a href="/novyny">посилання</a>.</p>\n'
        + '<ul>\n  <li>Один</li>\n  <li>Два</li>\n</ul>\n',
    );
});

test('таблиці й розгортні блоки отримують вкладені відступи', () => {
    assert.equal(
        formatHtml('<details><summary>Більше</summary><table><tbody><tr><td>1</td><td style="width:50%">2</td></tr></tbody></table></details>'),
        '<details>\n  <summary>Більше</summary>\n  <table>\n    <tbody>\n      <tr>\n'
        + '        <td>1</td>\n        <td style="width:50%">2</td>\n'
        + '      </tr>\n    </tbody>\n  </table>\n</details>\n',
    );
});

test('текст, інлайн-теги, pre та коментарі не змінюються', () => {
    const source = '<p>Рядок<br>другий  рядок  <em>курсив</em></p><pre>  код\n    з відступом</pre><!--imported-from:https://otfk.od.ua/x--><p>a > b</p>';
    const formatted = formatHtml(source);

    assert.match(formatted, /<pre>  код\n    з відступом<\/pre>/);
    assert.match(formatted, /\n<!--imported-from:https:\/\/otfk\.od\.ua\/x-->\n/);
    assert.match(formatted, /другий {2}рядок {2}<em>курсив<\/em>/);
    assert.equal(visible(formatted.replace(/<pre>[\s\S]*<\/pre>/, '')), visible(source.replace(/<pre>[\s\S]*<\/pre>/, '')));
});

test('змішаний вміст і атрибути з > у лапках', () => {
    const source = '<ul><li>Пункт<ul><li>Вкладений</li></ul></li></ul><p><img src="/a.png" alt="x > y"> підпис</p>';
    const formatted = formatHtml(source);

    assert.equal(formatted, '<ul>\n  <li>Пункт\n    <ul>\n      <li>Вкладений</li>\n    </ul>\n  </li>\n</ul>\n<p><img src="/a.png" alt="x > y"> підпис</p>\n');
    assert.equal(visible(formatted), visible(source));
});

test('форматування ідемпотентне, порожній текст лишається порожнім', () => {
    const source = '<h2>A</h2><p>Б <b>В</b></p><table><tr><td>Г<br>Д</td></tr></table><div><p>Е</p>текст</div>';
    const once = formatHtml(source);

    assert.equal(formatHtml(once), once);
    assert.equal(formatHtml(''), '');
    assert.equal(formatHtml(null), '');
});
