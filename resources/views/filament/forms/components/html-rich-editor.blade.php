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
            {{-- Зміни TipTap приймаються в стан лише після дії користувача у візуальному редакторі --}}
            touched: false,
            get lossy() {
                return new RegExp(@js(HtmlRichEditor::LOSSY), 'i').test(this.source ?? '')
            },
            {{-- CodeMirror (resources/js/admin/html-editor.js); без нього лишається звичайний textarea --}}
            codeReady: false,
            pending: null,
            timer: null,
            init() {
                this.remember(this.html)
                this.$watch('html', (value) => {
                    if (typeof value === 'string' || value === null) {
                        this.remember(value)
                        return
                    }
                    {{-- TipTap сам змінив документ (нормалізація, службовий вузол) — повертаємо незмінений HTML --}}
                    if (this.mode !== 'visual' || ! this.touched) this.write(this.source)
                })
            },
            remember(value) {
                if (typeof value !== 'string' && value !== null) return
                this.source = value ?? ''
                const found = this.source.match(/<!--imported-from:[^>]*-->/g) ?? []
                this.markers = [...new Set([...this.markers, ...found])]
            },
            {{-- Компонент RichEditor усередині поля (Alpine-дані richEditorFormComponent) --}}
            rich() {
                const el = this.$root.querySelector('[x-data^=richEditorFormComponent]')
                return el && window.Alpine ? window.Alpine.$data(el) : null
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
                    this.rich()?.getEditor()?.commands.setContent(this.source, { emitUpdate: false })
                }
                this.touched = false
            },
            async showCode() {
                const tools = window.otfkHtmlEditor
                if (! tools) return
                const text = tools.formatHtml(this.source)
                const holder = this.$refs.code
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
                tools && this.$refs.code._otfkEditor?.replaceSelection((selected) => tools.formatHtml(tools.detailsSnippet(selected)).trimEnd())
            },
            sectionsToDetails() {
                const tools = window.otfkHtmlEditor
                const editor = this.$refs.code._otfkEditor
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
            touch(event) {
                if (this.mode !== 'visual' || this.touched) return
                if (this.lossy && ! confirm(@js('Візуальний редактор спростить вбудовані фрейми, оформлення (class/style), ширини таблиць і службові коментарі цього тексту. Редагувати тут усе одно? «Скасувати» — перейти до режиму HTML.'))) {
                    event.preventDefault()
                    event.stopImmediatePropagation()
                    this.setMode('html')
                    return
                }
                this.touched = true
            },
        }"
        x-on:keydown.capture="if ($event.target.closest('.fi-fo-rich-editor-content')) keyTouch($event)"
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
            Текст містить вбудовані фрейми, імпортоване оформлення (class/style), ширини таблиць або службові коментарі,
            яких візуальний редактор не зберігає: правки тут їх спростять. Для таких текстів користуйтеся режимом HTML.
        </p>

        <div x-show="mode === 'html'" x-cloak wire:ignore class="otfk-html-source" x-on:focusout="flush()">
            <div x-show="codeReady" class="otfk-html-tools" role="toolbar" aria-label="Вставлення в HTML">
                <button type="button" x-on:click="insertDetails()" title="Вставити розгортний блок; виділений HTML стане його вмістом, заголовок на початку виділення — підписом">+ Розгортний блок</button>
                <button type="button" x-on:click="sectionsToDetails()" title="Кожен заголовок найвищого рівня разом із вмістом під ним стане окремим розгортним блоком">Розділи → розгортні блоки</button>
            </div>
            <div x-ref="code"></div>
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
