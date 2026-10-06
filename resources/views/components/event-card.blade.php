@props(['event'])

{{-- Картка події для сітки «Найближчі події»: блок дати у navy + час, місце та кнопки календаря --}}
@php
    $sameDay = ! $event->ends_at || $event->ends_at->isSameDay($event->starts_at);

    $meta = array_filter([
        ['clock', $sameDay
            ? $event->starts_at->format('H:i') . ($event->ends_at ? '–' . $event->ends_at->format('H:i') : '')
            : $event->starts_at->format('H:i') . ' – ' . $event->ends_at->translatedFormat('j F, H:i')],
        ['map-pin', $event->localized('location')],
    ], fn ($row) => filled($row[1]));
@endphp

<article {{ $attributes->class(['card flex flex-col overflow-hidden transition hover:shadow-lg hover:shadow-slate-300/40']) }}>
    <div class="flex flex-1 flex-col p-6">
        <div class="flex items-start justify-between gap-4">
            <div class="shrink-0 rounded-2xl bg-gradient-to-br from-brand-800 to-brand-950 px-4 py-3 text-center text-white shadow-sm">
                <span class="block font-display text-3xl font-extrabold leading-none">{{ $event->starts_at->format('d') }}</span>
                <span class="mt-1 block text-xs font-semibold uppercase text-gold-300">{{ $event->starts_at->translatedFormat('M') }}</span>
            </div>
            <span class="mt-1 text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $event->starts_at->translatedFormat('l') }}</span>
        </div>

        <h3 class="mt-5 text-lg font-bold leading-snug text-brand-950">{{ $event->localized('title') }}</h3>

        @if ($meta)
            <ul class="mt-3 space-y-2 text-sm">
                @foreach ($meta as [$icon, $value])
                    <li class="flex items-start gap-2 text-slate-600">
                        <x-ico :name="$icon" class="mt-0.5 h-4 w-4 shrink-0 text-brand-500" aria-hidden="true" />
                        <span class="min-w-0">{{ $value }}</span>
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($event->localized('description'))
            <p class="mt-4 line-clamp-4 text-sm leading-relaxed text-slate-500">{{ $event->localized('description') }}</p>
        @endif

        <div class="mt-6 flex flex-1 flex-col justify-end gap-3">
            <x-event-calendar-links :event="$event" />

            @if ($event->url)
                <a href="{{ $event->url }}" @if (! str_starts_with($event->url, \App\Support\LocalizedUrl::to('/'))) target="_blank" rel="noopener" @endif
                   class="inline-flex items-center gap-1.5 self-start text-sm font-semibold text-brand-700 transition hover:gap-2.5">
                    {{ __('public.details') }} <x-ico name="arrow-right" class="h-4 w-4" />
                </a>
            @endif
        </div>
    </div>
</article>
