{{-- Перемикач режимів HtmlRichEditor: працює в Alpine-області обгортки поля --}}
<span class="otfk-mode-toggle" role="group" aria-label="Режим редактора">
    <button type="button" x-on:click="setMode('visual')" x-bind:aria-pressed="mode === 'visual'">Візуально</button>
    <button type="button" x-on:click="setMode('html')" x-bind:aria-pressed="mode === 'html'" class="otfk-mode-toggle__html">HTML</button>
</span>
