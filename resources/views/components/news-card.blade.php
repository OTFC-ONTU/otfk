@props(['item', 'featured' => false])

@if ($featured)
    {{-- Головна новина сторінки — велика горизонтальна картка на всю ширину сітки --}}
    <article class="card card-interactive group grid overflow-hidden md:grid-cols-2">
        <a href="{{ \App\Support\LocalizedUrl::route('news.show', $item) }}" class="relative block aspect-[16/9] overflow-hidden md:aspect-auto md:min-h-[20rem]">
            @if ($item->cover_image)
                <x-picture :path="$item->cover_image" :alt="$item->localized('title')" loading="eager" decoding="async"
                           class="h-full w-full object-cover transition duration-500 group-hover:scale-105" />
            @else
                <div class="flex h-full w-full items-center justify-center bg-gradient-to-br from-brand-700 to-brand-900 text-white/25">
                    <x-ico name="newspaper" class="h-16 w-16" />
                </div>
            @endif
            @if ($item->category)
                <span class="badge absolute left-4 top-4 bg-brand-950/80 text-white backdrop-blur-sm">{{ $item->category->localized('title') }}</span>
            @endif
        </a>
        <div class="flex flex-col justify-center p-6 lg:p-10">
            @if ($item->published_at)
                <span class="text-sm text-slate-400">{{ $item->published_at->translatedFormat('j F Y') }}</span>
            @endif
            <h2 class="mt-3 text-2xl font-extrabold leading-tight lg:text-3xl">
                <a href="{{ \App\Support\LocalizedUrl::route('news.show', $item) }}" class="text-slate-900 transition hover:text-brand-700">{{ $item->localized('title') }}</a>
            </h2>
            <p class="mt-4 line-clamp-4 leading-relaxed text-slate-500">{{ $item->localized('excerpt') }}</p>
            <div class="mt-6 flex items-center justify-between gap-3">
                <a href="{{ \App\Support\LocalizedUrl::route('news.show', $item) }}" class="inline-flex items-center gap-1.5 font-semibold text-brand-700 transition hover:gap-2.5">
                    {{ __('public.read_more') }} <x-ico name="arrow-right" class="h-4 w-4" />
                </a>
                <span class="inline-flex items-center gap-3 text-sm text-slate-400">
                    <span class="inline-flex items-center gap-1" title="{{ __('public.views') }}"><x-ico name="eye" class="h-4 w-4" /> {{ $item->views }}</span>
                    <span class="inline-flex items-center gap-1" title="{{ __('public.likes') }}"><x-ico name="heart" class="h-4 w-4" /> {{ (int) $item->likes }}</span>
                </span>
            </div>
        </div>
    </article>
@else
    <article class="card card-interactive group flex flex-col overflow-hidden">
        <a href="{{ \App\Support\LocalizedUrl::route('news.show', $item) }}" class="relative block aspect-[16/9] overflow-hidden">
            @if ($item->cover_image)
                <x-picture :path="$item->cover_image" :alt="$item->localized('title')" loading="lazy" decoding="async"
                           class="h-full w-full object-cover transition duration-500 group-hover:scale-105" />
            @else
                <div class="flex h-full w-full items-center justify-center bg-gradient-to-br from-brand-700 to-brand-900 text-white/25">
                    <x-ico name="newspaper" class="h-12 w-12" />
                </div>
            @endif
            @if ($item->category)
                <span class="badge absolute left-3 top-3 bg-brand-950/80 text-white backdrop-blur-sm">{{ $item->category->localized('title') }}</span>
            @endif
        </a>
        <div class="flex flex-1 flex-col p-5">
            @if ($item->published_at)
                <span class="text-xs text-slate-400">{{ $item->published_at->translatedFormat('j F Y') }}</span>
            @endif
            <h3 class="mt-2.5 line-clamp-2 text-lg font-bold leading-snug">
                <a href="{{ \App\Support\LocalizedUrl::route('news.show', $item) }}" class="text-slate-900 transition hover:text-brand-700">{{ $item->localized('title') }}</a>
            </h3>
            <p class="mt-2 line-clamp-3 text-sm leading-relaxed text-slate-500">{{ $item->localized('excerpt') }}</p>
            <div class="mt-auto flex items-center justify-between gap-3 pt-4">
                <a href="{{ \App\Support\LocalizedUrl::route('news.show', $item) }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand-700 transition hover:gap-2.5">
                    {{ __('public.read_more') }} <x-ico name="arrow-right" class="h-4 w-4" />
                </a>
                <span class="inline-flex items-center gap-3 text-xs text-slate-400">
                    <span class="inline-flex items-center gap-1" title="{{ __('public.views') }}"><x-ico name="eye" class="h-3.5 w-3.5" /> {{ $item->views }}</span>
                    <span class="inline-flex items-center gap-1" title="{{ __('public.likes') }}"><x-ico name="heart" class="h-3.5 w-3.5" /> {{ (int) $item->likes }}</span>
                </span>
            </div>
        </div>
    </article>
@endif
