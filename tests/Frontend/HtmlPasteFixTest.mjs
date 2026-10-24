import { test } from 'node:test';
import assert from 'node:assert/strict';
import { fixPastedBlocks } from '../../resources/js/admin/html-paste-fix.js';

const text = (value, marks) => ({ type: 'text', text: value, ...(marks ? { marks } : {}) });
const br = { type: 'hardBreak' };
const p = (content, attrs) => ({ type: 'paragraph', ...(attrs ? { attrs } : {}), content });
const li = (value) => ({ type: 'listItem', content: [p([text(value)])] });

test('абзац із переносами й «•» (вставка з Telegram) стає абзацами та списком без вирівнювання пунктів', () => {
    const { blocks, changed } = fixPastedBlocks([
        p([text('Вступ.'), br, text('Програма: '), br, text('• Перший; '), br, text('• Другий.'), br, text('Висновок.')], { textAlign: 'justify' }),
    ]);

    assert.equal(changed, true);
    assert.deepEqual(blocks, [
        p([text('Вступ.')], { textAlign: 'justify' }),
        p([text('Програма:')], { textAlign: 'justify' }),
        { type: 'bulletList', content: [
            { type: 'listItem', content: [p([text('Перший;')])] },
            { type: 'listItem', content: [p([text('Другий.')])] },
        ] },
        p([text('Висновок.')], { textAlign: 'justify' }),
    ]);
});

test('позначки тексту зберігаються, маркер знімається з першого текстового вузла', () => {
    const bold = [{ type: 'bold' }];
    const { blocks } = fixPastedBlocks([p([text('• '), text('Жирний', bold), text(' пункт')])]);
    assert.deepEqual(blocks, [{ type: 'bulletList', content: [{ type: 'listItem', content: [p([text('Жирний', bold), text(' пункт')])] }] }]);
});

test('тире та нумерація — список лише серією; одиночне тире (пряма мова) лишається абзацом', () => {
    assert.deepEqual(fixPastedBlocks([p([text('– Добрий день, — сказав він.')])]).changed, false);

    const dashes = fixPastedBlocks([p([text('- один')]), p([text('- два')])]).blocks;
    assert.deepEqual(dashes, [{ type: 'bulletList', content: [li('один'), li('два')] }]);

    const numbers = fixPastedBlocks([p([text('3. три')]), p([text('4) чотири')]), p([text('2025. рік')])]).blocks;
    assert.deepEqual(numbers, [
        { type: 'orderedList', attrs: { start: 3 }, content: [li('три'), li('чотири')] },
        p([text('2025. рік')]),
    ]);
});

test('нумеровані посилання на файли не стають списком (номер у назві картки)', () => {
    const link = [{ type: 'link', attrs: { href: '/storage/a.pdf' } }];
    const source = [p([text('1) Наказ', link)]), p([text('2) Положення', link)])];
    assert.equal(fixPastedBlocks(source).changed, false);
});

test('порожні абзаци прибираються, але контейнер не лишається порожнім', () => {
    assert.deepEqual(fixPastedBlocks([p([text('а')]), p([]), p([text('  ')]), p([text('б')])]).blocks, [p([text('а')]), p([text('б')])]);
    assert.deepEqual(fixPastedBlocks([p([])]).blocks, [p([])]);
});

test('вкладені блоки обробляються, у пункті списку «•» не стає вкладеним списком', () => {
    const { blocks } = fixPastedBlocks([
        { type: 'blockquote', content: [p([text('• а'), br, text('• б')])] },
        { type: 'bulletList', content: [{ type: 'listItem', content: [p([text('• в'), br, text('г')])] }] },
    ]);
    assert.deepEqual(blocks, [
        { type: 'blockquote', content: [{ type: 'bulletList', content: [li('а'), li('б')] }] },
        { type: 'bulletList', content: [{ type: 'listItem', content: [p([text('• в')]), p([text('г')])] }] },
    ]);
});

test('уже охайний текст не змінюється', () => {
    const source = [p([text('Абзац')]), { type: 'bulletList', content: [li('пункт')] }, { type: 'heading', attrs: { level: 2 }, content: [text('Заголовок')] }];
    assert.deepEqual(fixPastedBlocks(source), { blocks: source, changed: false });
});

test('пункт списку лишає порожній перший абзац (схема «paragraph block*»)', () => {
    const item = { type: 'listItem', content: [p([]), { type: 'bulletList', content: [li('вкладений')] }] };
    const { blocks } = fixPastedBlocks([{ type: 'bulletList', content: [item] }]);
    assert.deepEqual(blocks[0].content[0].content[0], p([]));
});

test('рядки з «•» у клітинці таблиці стають списком', () => {
    const cell = { type: 'tableCell', content: [p([text('• один'), br, text('• два')])] };
    const { blocks } = fixPastedBlocks([{ type: 'table', content: [{ type: 'tableRow', content: [cell] }] }]);
    assert.deepEqual(blocks[0].content[0].content[0].content, [{ type: 'bulletList', content: [li('один'), li('два')] }]);
});
