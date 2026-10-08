{{-- TipTap (RichEditor Filament 4) + режим «чистого» HTML на тому самому стані (App\Filament\Forms\Components\HtmlRichEditor) --}}
@php
    use App\Filament\Forms\Components\HtmlRichEditor;

    $statePath = $getStatePath();
@endphp

@if ($isDisabled())
    {!! $field->toEmbeddedHtml() !!}
@else
    <div
        x-data="{
            mode: 'visual',
            {{-- HTML-рядок як у БД; документ TipTap (об'єкт) з'являється лише після правки у візуальному режимі --}}
            html: $wire.$entangle(@js($statePath), false),
            source: '',
            markers: [],
            {{-- Документ TipTap одразу після завантаження тексту (нормалізований HTML): зміна стану, що не відрізняється
                 від нього, — службова нормалізація TipTap, а не правка користувача --}}
            baseline: null,
            {{-- Користувач погодився на спрощення «lossy»-тексту у візуальному режимі --}}
            confirmed: false,
            get lossy() {
                return new RegExp(@js(HtmlRichEditor::LOSSY), 'i').test(this.source ?? '')
            },
            {{-- CodeMirror (resources/js/admin/html-editor.js); без нього лишається звичайний textarea --}}
            codeReady: false,
            pending: null,
            timer: null,
            init() {
                this.remember(this.html)
                this.whenEditor((editor) => { this.baseline = this.normalized(editor) })
                this.$watch('html', (value) => {
                    if (typeof value === 'string' || value === null) {
                        this.remember(value)
                        return
                    }
                    const editor = this.rich()?.getEditor()
                    {{-- Документ не змінився по суті (лише нормалізація TipTap) — лишаємо вихідний HTML без змін --}}
                    if (this.mode !== 'visual' || ! editor || this.baseline === null || this.normalized(editor) === this.baseline) {
                        this.write(this.source)
                        return
                    }
                    {{-- Справжня правка будь-яким способом (клавіатура, панель, перетягування, розмір зображення) --}}
                    if (this.lossy && ! this.confirmed) {
                        if (! confirm(this.lossyMessage)) {
                            editor.commands.setContent(this.source, { emitUpdate: false })
                            this.write(this.source)
                            this.setMode('html')
                            return
                        }
                        this.confirmed = true
                    }
                })
            },
            lossyMessage: @js('Візуальний редактор спростить оформлення (class/style), ширини таблиць, якорі (id) і службові коментарі цього тексту. Редагувати тут усе одно? «Скасувати» — перейти до режиму HTML.'),
            {{-- Без ліміту: TipTap вантажиться лінивим чанком (x-load), на повільному з'єднанні — довго;
                 до появи знімка (baseline) правки неможливі, бо редактора ще немає --}}
            whenEditor(callback, delay = 100) {
                const editor = this.rich()?.getEditor()
                if (editor) return callback(editor)
                setTimeout(() => this.whenEditor(callback, Math.min(delay * 2, 1000)), delay)
            },
            {{-- HTML документа TipTap без службової розмітки й порожніх абзаців у кінці (TrailingNode) --}}
            normalized(editor) {
                const html = editor.getHTML()
                return (window.otfkHtmlEditor?.cleanEditorHtml(html) ?? html).replace(/(?:<p><\/p>)+$/, '').trim()
            },
            remember(value) {
                if (typeof value !== 'string' && value !== null) return
                this.source = value ?? ''
                const found = this.source.match(/<!--imported-from:[^>]*-->/g) ?? []
                this.markers = [...new Set([...this.markers, ...found])]
            },
            {{-- Обгортка поля: $root/$refs з кнопки перемикача в підказці вказують на інший x-data (обгортку поля Filament) --}}
            root() {
                return this.$el.closest('.otfk-html-rich-editor')
            },
            code() {
                return this.root()?.querySelector('.otfk-html-source [data-otfk-code]')
            },
            {{-- Компонент RichEditor усередині поля (Alpine-дані richEditorFormComponent) --}}
            rich() {
                const el = this.root()?.querySelector('[x-data^=richEditorFormComponent]')
                const data = el && window.Alpine ? window.Alpine.$data(el) : null
                {{-- До ініціалізації (x-load) $data повертає дані батьківського компонента — тобто цієї обгортки --}}
                return typeof data?.getEditor === 'function' ? data : null
            },
            {{-- Запис рядка в стан без повторного завантаження в TipTap (інакше він одразу поверне нормалізований документ) --}}
            write(value) {
                const rich = this.rich()
                if (rich) rich.shouldUpdateState = false
                this.html = value
                this.source = value ?? ''
                setTimeout(() => { if (rich) rich.shouldUpdateState = true })
            },
            {{-- Поточний вміст як HTML: після правки у візуальному режимі — з TipTap, з маркерами імпорту --}}
            currentHtml() {
                if (typeof this.html === 'string' || this.html === null) return this.html ?? ''
                const editorHtml = this.rich()?.getEditor()?.getHTML()
                let html = editorHtml ? (window.otfkHtmlEditor?.cleanEditorHtml(editorHtml) ?? editorHtml) : this.source
                for (const marker of this.markers) if (! html.includes(marker)) html += '\n' + marker
                return html
            },
            setMode(mode) {
                this.flush()
                if (mode === this.mode) return
                if (mode === 'html') {
                    if (typeof this.html !== 'string' && this.html !== null) this.write(this.currentHtml())
                    this.mode = 'html'
                    this.$nextTick(() => this.showCode())
                } else {
                    this.mode = 'visual'
                    const editor = this.rich()?.getEditor()
                    editor?.commands.setContent(this.source, { emitUpdate: false })
                    this.baseline = editor ? this.normalized(editor) : null
                }
                this.confirmed = false
            },
            async showCode() {
                const tools = window.otfkHtmlEditor
                if (! tools) return
                const text = tools.formatHtml(this.source)
                const holder = this.code()
                if (holder._otfkEditor) {
                    holder._otfkEditor.setDoc(text)
                    return
                }
                try {
                    holder._otfkEditor = await tools.mount(holder, {
                        doc: text,
                        label: @js($getLabel().' (HTML)'),
                        onChange: (value) => this.queue(value),
                    })
                    this.codeReady = true
                } catch (error) {
                    console.error(error)
                }
            },
            {{-- Розгортні блоки (resources/js/admin/html-sections.js): вставка та розділи за заголовками --}}
            insertDetails() {
                const tools = window.otfkHtmlEditor
                tools && this.code()._otfkEditor?.replaceSelection((selected) => tools.formatHtml(tools.detailsSnippet(selected)).trimEnd())
            },
            sectionsToDetails() {
                const tools = window.otfkHtmlEditor
                const editor = this.code()._otfkEditor
                if (! tools || ! editor) return
                const { html, count } = tools.headingsToDetails(editor.getDoc())
                if (! count) {
                    alert(@js('Не знайдено заголовків (H2–H6) із вмістом під ними поза розгортними блоками.'))
                    return
                }
                if (! confirm(@js('Перетворити розділи на розгортні блоки:') + ' ' + count + '? ' + @js('Заголовки стануть підписами блоків, вміст до наступного такого ж заголовка — їхнім текстом. Скасувати можна через Ctrl/Cmd+Z.'))) return
                editor.replaceDoc(tools.formatHtml(html))
            },
            {{-- Стан оновлюється з паузою і одразу — при виході з поля --}}
            queue(value) {
                this.pending = value
                clearTimeout(this.timer)
                this.timer = setTimeout(() => this.flush(), 400)
            },
            flush() {
                clearTimeout(this.timer)
                if (this.pending === null) return
                this.write(this.pending)
                this.pending = null
            },
            {{-- Навігація та копіювання текст не змінюють --}}
            keyTouch(event) {
                const key = event.key ?? ''
                if (['Tab', 'Shift', 'Control', 'Alt', 'Meta', 'Escape', 'Home', 'End', 'PageUp', 'PageDown'].includes(key) || key.startsWith('Arrow')) return
                if ((event.metaKey || event.ctrlKey) && ['c', 'a', 'f'].includes(key.toLowerCase())) return
                this.touch(event)
            },
            {{-- Для «lossy»-тексту підтвердження ще до першої дії, щоб правку можна було не почати --}}
            touch(event) {
                if (this.mode !== 'visual' || this.confirmed || ! this.lossy) return
                if (! confirm(this.lossyMessage)) {
                    event.preventDefault()
                    event.stopImmediatePropagation()
                    this.setMode('html')
                    return
                }
                this.confirmed = true
            },
        }"
        x-on:keydown.capture="if ($event.target.closest('.fi-fo-rich-editor-content')) keyTouch($event)"
        x-on:keydown.window.capture="if (($event.metaKey || $event.ctrlKey) && ($event.key ?? '').toLowerCase() === 's') flush()"
        x-on:beforeinput.capture="if ($event.target.closest('.fi-fo-rich-editor-content')) touch($event)"
        x-on:paste.capture="if ($event.target.closest('.fi-fo-rich-editor-content')) touch($event)"
        x-on:drop.capture="if ($event.target.closest('.fi-fo-rich-editor-content')) touch($event)"
        x-on:cut.capture="if ($event.target.closest('.fi-fo-rich-editor-content')) touch($event)"
        x-on:mousedown.capture="if ($event.target.closest('.fi-fo-rich-editor-toolbar button, .fi-fo-rich-editor-floating-toolbar button, .fi-fo-rich-editor-panels button')) touch($event)"
        x-on:click.capture="if ($event.target.closest('.fi-fo-rich-editor-toolbar button, .fi-fo-rich-editor-floating-toolbar button, .fi-fo-rich-editor-panels button')) touch($event)"
        x-bind:class="{ 'otfk-html-mode': mode === 'html' }"
        class="otfk-html-rich-editor"
    >
        {!! $field->toEmbeddedHtml() !!}

        <p x-show="mode === 'visual' && lossy" x-cloak class="otfk-lossy-note">
            Текст містить імпортоване оформлення (class/style), ширини таблиць, якорі (id) або службові коментарі,
            яких візуальний редактор не зберігає: правки тут їх спростять. Для таких текстів користуйтеся режимом HTML.
        </p>

        <div x-show="mode === 'html'" x-cloak wire:ignore class="otfk-html-source" x-on:focusout="flush()">
            <div x-show="codeReady" class="otfk-html-tools" role="toolbar" aria-label="Вставлення в HTML">
                <button type="button" x-on:click="insertDetails()" title="Вставити розгортний блок; виділений HTML стане його вмістом, заголовок на початку виділення — підписом">+ Розгортний блок</button>
                <button type="button" x-on:click="sectionsToDetails()" title="Кожен заголовок найвищого рівня разом із вмістом під ним стане окремим розгортним блоком">Розділи → розгортні блоки</button>
            </div>
            <div data-otfk-code></div>
            <textarea x-show="! codeReady"
                x-bind:value="source"
                x-on:change="write($event.target.value)"
                rows="20"
                spellcheck="false"
                aria-label="{{ $getLabel() }} (HTML)"
            ></textarea>
        </div>
    </div>

    @once
        <style>
            .otfk-html-rich-editor.otfk-html-mode .fi-fo-rich-editor { display: none; }
            /* Текст у редакторі — колонкою завширшки як на сайті (≈720 px), як аркуш документа */
            .otfk-html-rich-editor .fi-fo-rich-editor-content { max-width: 45rem; margin-inline: auto; }
            .otfk-html-rich-editor .fi-fo-rich-editor-content img { max-width: 100%; }
            /* Зображення без заданого розміру на сайті займає всю колонку; у редакторі — компактне прев'ю з поясненням */
            .otfk-html-rich-editor .fi-fo-rich-editor-content img:not([style*="width"]) { max-height: 16rem; width: auto; }
            .otfk-html-rich-editor [data-resize-wrapper]:has(> img:not([style*="width"]))::after {
                content: 'На сайті — на всю ширину колонки. Виділіть, щоб вибрати розмір';
                position: absolute; left: .5rem; bottom: .5rem; max-width: calc(100% - 1rem); padding: .125rem .5rem;
                border-radius: .375rem; background: rgb(17 24 39 / .75); color: #fff; font-size: .6875rem; line-height: 1.4;
                pointer-events: none;
            }
            .otfk-html-source { margin-top: .5rem; }
            .otfk-html-tools { display: flex; flex-wrap: wrap; gap: .375rem; margin-bottom: .375rem; }
            .otfk-html-tools button {
                padding: .125rem .625rem; border-radius: .375rem; font-size: .75rem; font-weight: 600; color: rgb(55 65 81);
                box-shadow: 0 0 0 1px rgb(3 7 18 / .12); background: #fff;
            }
            .otfk-html-tools button:hover { background: rgb(249 250 251); }
            .dark .otfk-html-tools button { color: rgb(229 231 235); background: rgb(255 255 255 / .05); box-shadow: 0 0 0 1px rgb(255 255 255 / .2); }
            .otfk-html-source .cm-editor { box-shadow: 0 0 0 1px rgb(3 7 18 / .1); }
            .dark .otfk-html-source .cm-editor { box-shadow: 0 0 0 1px rgb(255 255 255 / .2); }
            .otfk-lossy-note {
                margin-top: .5rem; padding: .5rem .75rem; border-radius: .5rem; font-size: .8125rem; line-height: 1.4;
                background: rgb(255 251 235); color: rgb(146 64 14); box-shadow: 0 0 0 1px rgb(253 230 138);
            }
            .dark .otfk-lossy-note { background: rgb(120 53 15 / .25); color: rgb(253 230 138); box-shadow: 0 0 0 1px rgb(146 64 14 / .6); }
            .otfk-html-source textarea {
                display: block; width: 100%; resize: vertical; border: 0; border-radius: .5rem;
                padding: .5rem .75rem; background: #fff; color: #030712;
                font: 12px/1.6 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
                box-shadow: 0 0 0 1px rgb(3 7 18 / .1), 0 1px 2px rgb(0 0 0 / .05);
            }
            .otfk-html-source textarea:focus { outline: none; box-shadow: 0 0 0 2px var(--primary-600); }
            .dark .otfk-html-source textarea { background: rgb(255 255 255 / .05); color: #fff; box-shadow: 0 0 0 1px rgb(255 255 255 / .2); }
            .otfk-mode-toggle { display: inline-flex; gap: 2px; padding: 2px; border-radius: .5rem; box-shadow: 0 0 0 1px rgb(3 7 18 / .1); }
            .dark .otfk-mode-toggle { box-shadow: 0 0 0 1px rgb(255 255 255 / .2); }
            .otfk-mode-toggle button { padding: 1px .5rem; border-radius: .375rem; font-size: .75rem; font-weight: 600; color: rgb(75 85 99); }
            .dark .otfk-mode-toggle button { color: rgb(209 213 219); }
            .otfk-mode-toggle button[aria-pressed="true"] { background: var(--primary-600); color: #fff; }
            .otfk-mode-toggle__html { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
        </style>
    @endonce
@endif
