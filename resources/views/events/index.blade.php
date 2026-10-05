<x-layouts.app :title="__('public.events')" :description="__('public.events_description')">

    {{-- Розмітка Event: Google може показувати події у видачі з датою та місцем --}}
    @if ($upcoming->isNotEmpty())
        @php
            $eventsLd = $upcoming->map(fn ($e) => array_filter([
                '@context' => 'https://schema.org',
                '@type' => 'Event',
                'name' => $e->localized('title'),
                'startDate' => $e->starts_at->copy()->shiftTimezone('Europe/Kyiv')->toIso8601String(),
                'endDate' => $e->ends_at?->copy()->shiftTimezone('Europe/Kyiv')->toIso8601String(),
                'description' => $e->localized('description'),
                'eventStatus' => 'https://schema.org/EventScheduled',
                'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
                'location' => [
                    '@type' => 'Place',
                    'name' => $e->localized('location') ?: config('app.name'),
                    'address' => \App\Models\Setting::publicGet('contact_address') ?: __('public.city_address'),
                ],
                'organizer' => ['@type' => 'Organization', 'name' => config('app.name'), 'url' => \App\Support\LocalizedUrl::to('/')],
                'url' => \App\Support\LocalizedUrl::route('events'),
            ]))->values()->all();
        @endphp
        <script type="application/ld+json">{!! json_encode($eventsLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
    @endif

    <x-page-hero :title="__('public.events_title')" :breadcrumbs="[
        ['label' => __('public.home'), 'url' => \App\Support\LocalizedUrl::route('home')],
        ['label' => __('public.events')],
    ]" />

    <section class="container-site py-12">
        @if ($upcoming->isEmpty() && $past->isEmpty())
            <x-empty-state icon="calendar-days" :title="__('public.no_events')" />
        @endif

        @if ($upcoming->isNotEmpty())
            <h2 class="text-2xl font-extrabold text-slate-900">{{ __('public.upcoming_events') }}</h2>
            <div class="accent-rule"></div>
            <div class="mt-7 space-y-4">
                @foreach ($upcoming as $event)
                    <div class="card flex flex-col gap-4 p-5 sm:flex-row sm:items-start">
                        <div class="grid h-20 w-20 shrink-0 place-items-center rounded-2xl bg-brand-700 text-white shadow-sm">
                            <div class="text-center leading-none">
                                <div class="text-3xl font-extrabold">{{ $event->starts_at->format('d') }}</div>
                                <div class="mt-1 text-xs font-semibold uppercase">{{ $event->starts_at->translatedFormat('M Y') }}</div>
                            </div>
                        </div>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-bold text-slate-900">{{ $event->localized('title') }}</h3>
                            <p class="mt-1 text-sm text-slate-500">
                                <x-ico name="clock" class="-mt-0.5 inline h-4 w-4" />
                                {{ __('public.event_time', ['date' => $event->starts_at->translatedFormat('l, j F'), 'time' => $event->starts_at->format('H:i')]) }}
                                @if ($event->ends_at)
                                    – {{ $event->ends_at->isSameDay($event->starts_at) ? $event->ends_at->format('H:i') : $event->ends_at->translatedFormat('j F, H:i') }}
                                @endif
                                @if ($event->localized('location'))
                                    <span class="ml-1">· <x-ico name="map-pin" class="-mt-0.5 inline h-4 w-4" /> {{ $event->localized('location') }}</span>
                                @endif
                            </p>
                            @if ($event->localized('description'))
                                <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ $event->localized('description') }}</p>
                            @endif
                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                @if ($event->url)
                                    <a href="{{ \App\Support\LocalizedUrl::to($event->url) }}" @if (! str_starts_with($event->url, url('/'))) target="_blank" rel="noopener" @endif
                                       class="mr-2 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-700 hover:gap-2.5 transition">
                                        {{ __('public.details') }} <x-ico name="arrow-right" class="h-4 w-4" />
                                    </a>
                                @endif
                                {{-- Додати в календар --}}
                                <a href="{{ $event->google_calendar_url }}" target="_blank" rel="noopener"
                                   class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-600 ring-1 ring-slate-200 transition hover:bg-slate-200"
                                   title="{{ __('public.add_google_calendar') }}">
                                    <x-ico name="calendar-days" class="h-3.5 w-3.5" /> {{ __('public.google_calendar') }}
                                </a>
                                <a href="{{ \App\Support\LocalizedUrl::route('events.ics', $event) }}"
                                   class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-600 ring-1 ring-slate-200 transition hover:bg-slate-200"
                                   title="{{ __('public.download_ics') }}">
                                    <x-ico name="arrow-down-tray" class="h-3.5 w-3.5" /> .ics
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        @if ($past->isNotEmpty())
            <h2 class="mt-14 text-xl font-bold text-slate-400">{{ __('public.past_events') }}</h2>
            <div class="mt-5 space-y-2.5">
                @foreach ($past as $event)
                    <div class="flex items-center gap-3 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-500">
                        <span class="shrink-0 tabular-nums font-medium">{{ $event->starts_at->format('d.m.Y') }}</span>
                        <span class="min-w-0 truncate">{{ $event->localized('title') }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

</x-layouts.app>
