{{-- Trix + режим «чистого» HTML на тому самому стані (App\Filament\Forms\Components\HtmlRichEditor) --}}
@php
    use App\Filament\Forms\Components\HtmlRichEditor;

    $statePath = $getStatePath();
@endphp

@if ($isDisabled())
    @include('filament-forms::components.rich-editor')
@else
    <div
        x-data="{
            mode: 'visual',
            html: $wire.$entangle(@js($statePath), false),
            {{-- Зміни Trix пишуться в стан лише після дії користувача у візуальному редакторі --}}
            touched: false,
            get lossy() {
                const html = (this.html ?? '').replace(new RegExp(@js(HtmlRichEditor::TRIX_ATTACHMENT), 'gi'), '')
                return new RegExp(@js(HtmlRichEditor::TRIX_LOSSY), 'i').test(html)
            },
            {{-- CodeMirror (resources/js/admin/html-editor.js); без нього лишається звичайний textarea --}}
            codeReady: false,
            pending: null,
            timer: null,
            setMode(mode) {
                this.flush()
                this.mode = mode
                this.touched = false
                if (mode === 'html') this.$nextTick(() => this.showCode())
            },
            async showCode() {
                const tools = window.otfkHtmlEditor
                if (! tools) return
                const text = tools.formatHtml(this.html ?? '')
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
            {{-- Стан оновлюється з паузою (кожне оновлення перезавантажує Trix) і одразу — при виході з поля --}}
            queue(value) {
                this.pending = value
                clearTimeout(this.timer)
                this.timer = setTimeout(() => this.flush(), 400)
            },
            flush() {
                clearTimeout(this.timer)
                if (this.pending === null) return
                this.html = this.pending
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
                if (this.lossy && ! confirm(@js('Візуальний редактор спростить таблиці, розгортні блоки, вбудовані фрейми та оформлення цього тексту. Редагувати тут усе одно? «Скасувати» — перейти до режиму HTML.'))) {
                    event.preventDefault()
                    event.stopImmediatePropagation()
                    this.setMode('html')
                    return
                }
                this.touched = true
            },
        }"
        x-on:trix-change.capture="if (mode === 'html' || ! touched) $event.stopImmediatePropagation()"
        x-on:keydown.capture="if ($event.target.closest('trix-editor')) keyTouch($event)"
        x-on:paste.capture="if ($event.target.closest('trix-editor')) touch($event)"
        x-on:drop.capture="if ($event.target.closest('trix-editor')) touch($event)"
        x-on:cut.capture="if ($event.target.closest('trix-editor')) touch($event)"
        x-on:mousedown.capture="if ($event.target.closest('trix-toolbar button, trix-toolbar input')) touch($event)"
        x-bind:class="{ 'otfk-html-mode': mode === 'html' }"
        class="otfk-html-rich-editor"
    >
        @include('filament-forms::components.rich-editor')

        <p x-show="mode === 'visual' && lossy" x-cloak class="otfk-lossy-note">
            Текст містить таблиці, розгортні блоки або імпортоване оформлення, яких візуальний редактор не підтримує:
            правки тут їх спростять. Для таких текстів користуйтеся режимом HTML.
        </p>

        <div x-show="mode === 'html'" x-cloak wire:ignore class="otfk-html-source" x-on:focusout="flush()">
            <div x-ref="code"></div>
            <textarea x-show="! codeReady"
                x-model.lazy="html"
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
            .otfk-html-source textarea:focus { outline: none; box-shadow: 0 0 0 2px rgb(var(--primary-600)); }
            .dark .otfk-html-source textarea { background: rgb(255 255 255 / .05); color: #fff; box-shadow: 0 0 0 1px rgb(255 255 255 / .2); }
            .otfk-mode-toggle { display: inline-flex; gap: 2px; padding: 2px; border-radius: .5rem; box-shadow: 0 0 0 1px rgb(3 7 18 / .1); }
            .dark .otfk-mode-toggle { box-shadow: 0 0 0 1px rgb(255 255 255 / .2); }
            .otfk-mode-toggle button { padding: 1px .5rem; border-radius: .375rem; font-size: .75rem; font-weight: 600; color: rgb(75 85 99); }
            .dark .otfk-mode-toggle button { color: rgb(209 213 219); }
            .otfk-mode-toggle button[aria-pressed="true"] { background: rgb(var(--primary-600)); color: #fff; }
            .otfk-mode-toggle__html { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
        </style>
    @endonce
@endif
