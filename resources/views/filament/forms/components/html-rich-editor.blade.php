{{-- Trix + режим «чистого» HTML на тому самому стані (App\Filament\Forms\Components\HtmlRichEditor) --}}
@php
    $statePath = $getStatePath();
    $startsInHtml = \App\Filament\Forms\Components\HtmlRichEditor::needsHtmlMode($getState());
@endphp

@if ($isDisabled())
    @include('filament-forms::components.rich-editor')
@else
    <div
        x-data="{
            mode: @js($startsInHtml ? 'html' : 'visual'),
            html: $wire.$entangle(@js($statePath), false),
            toVisual() {
                if (this.mode === 'visual') return
                if (/<(table|details|iframe)\b|\s(class|style)\s*=/i.test(this.html ?? '')
                    && ! confirm(@js('Візуальний редактор спростить таблиці, розгортні блоки, вбудовані фрейми та оформлення цього тексту. Перемкнути все одно?'))) {
                    return
                }
                this.mode = 'visual'
            },
        }"
        {{-- У режимі HTML зміни Trix (зокрема нормалізація при завантаженні) не потрапляють у стан --}}
        x-on:trix-change.capture="if (mode === 'html') $event.stopImmediatePropagation()"
        x-bind:class="{ 'otfk-html-mode': mode === 'html' }"
        class="otfk-html-rich-editor"
    >
        @include('filament-forms::components.rich-editor')

        <div x-show="mode === 'html'" x-cloak wire:ignore class="otfk-html-source">
            <textarea
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
