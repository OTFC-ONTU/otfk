@props(['items' => [], 'tone' => 'dark'])

@php
    $items = array_map(fn ($item) => ! empty($item['url'])
        ? array_replace($item, ['url' => \App\Support\LocalizedUrl::to($item['url'])])
        : $item, $items);
    $count = count($items);
    $breadcrumbLd = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => collect($items)->values()->map(fn ($item, $i) => array_filter([
            '@type' => 'ListItem',
            'position' => $i + 1,
            'name' => $item['label'],
            'item' => (! empty($item['url']) && $i < $count - 1) ? $item['url'] : null,
        ], fn ($v) => $v !== null))->values()->all(),
    ];
@endphp

<nav aria-label="{{ __('public.breadcrumbs') }}" class="flex flex-wrap items-center gap-2 text-sm {{ $tone === 'light' ? 'text-slate-500' : 'text-brand-300' }}">
    @foreach ($items as $i => $item)
        @if ($i > 0)
            <x-ico name="chevron-right" class="h-4 w-4 shrink-0" aria-hidden="true" />
        @endif
        @if (! empty($item['url']) && $i < $count - 1)
            <a href="{{ $item['url'] }}" class="{{ $tone === 'light' ? 'hover:text-brand-700' : 'hover:text-white' }}">{{ $item['label'] }}</a>
        @else
            <span class="{{ $tone === 'light' ? 'text-slate-700' : 'text-white' }}" @if ($i === $count - 1) aria-current="page" @endif>{{ $item['label'] }}</span>
        @endif
    @endforeach
</nav>
<script type="application/ld+json">{!! json_encode($breadcrumbLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
