@props(['label' => null])

<nav aria-label="{{ $label ?? __('layout.language') }}" class="inline-flex shrink-0 items-center gap-1 text-xs font-semibold">
    @foreach (['uk' => 'УКР', 'en' => 'EN'] as $locale => $label)
        <a href="{{ \App\Support\LocalizedUrl::current($locale) }}" lang="{{ $locale }}"
           @click="$el.href = $el.href.split('#')[0] + window.location.hash"
           @pointerdown="$el.href = $el.href.split('#')[0] + window.location.hash"
           @if (app()->getLocale() === $locale) aria-current="true" @endif
           @class([
               'rounded-md px-2 py-2 transition',
               'bg-brand-700 text-white' => app()->getLocale() === $locale,
               'text-brand-700 hover:bg-brand-50' => app()->getLocale() !== $locale,
           ])>{{ $label }}</a>
    @endforeach
</nav>
