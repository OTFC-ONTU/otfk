@props(['excerpt' => null, 'body' => null, 'heritage' => false])

@php
    // НЕ показуємо лід-абзац, якщо тіло вже починається з того самого тексту —
    // інакше перший абзац дублюється на сторінці.
    $show = \App\Support\MetaText::clean($excerpt) !== '' && ! \App\Support\LeadExcerpt::repeatsBody($excerpt, $body);
@endphp

@if ($show)
    <p @class([
        'mb-6 max-w-3xl text-lg leading-relaxed',
        'font-heritage-display italic text-brand-800/90' => $heritage,
        'font-medium text-slate-600' => ! $heritage,
    ])>{{ $excerpt }}</p>
@endif
