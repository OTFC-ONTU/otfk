@props(['theme', 'size' => 28])

@php
    // Святковий значок біля логотипа: лінійна іконка акцентного кольору в колі
    // кольору навігації теми (стилі inline — працює й у прев'ю адмінки).
    $bg = $theme['nav'] ?? '#1f3568';
@endphp

<span {{ $attributes->class('hd-badge')->merge(['style' => "display:inline-grid;place-items:center;flex:none;width:{$size}px;height:{$size}px;border-radius:9999px;background:{$bg};box-shadow:0 0 0 2px #fff,0 2px 6px rgb(15 23 42 / .25);"]) }} aria-hidden="true">
    <x-holiday.motif :theme="$theme" :width="round($size * 0.62)" :height="round($size * 0.62)" style="color:{{ $theme['accent'] }}" />
</span>
