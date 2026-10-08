<x-layouts.app title="{{ __('public.events') }}" description="{{ __('feature.college_event_calendar_open_days_conferences_and') }}">

    @php
        // Українське відмінювання: 1 подія · 2–4 події · 5+ подій
        $eventWord = function (int $n): string {
            $mod100 = $n % 100;
            $mod10 = $n % 10;

            if ($mod100 >= 11 && $mod100 <= 14) {
                return __('feature.events');
            }

            return match (true) {
                $mod10 === 1 => __('feature.event'),
                $mod10 >= 2 && $mod10 <= 4 => __('feature.events_2'),
                default => __('feature.events'),
            };
        };

        $dayWord = function (int $n): string {
            $mod100 = $n % 100;
            $mod10 = $n % 10;

            if ($mod100 >= 11 && $mod100 <= 14) {
                return __('feature.days');
            }

            return match (true) {
                $mod10 === 1 => __('feature.day'),
                $mod10 >= 2 && $mod10 <= 4 => __('feature.days_2'),
                default => __('feature.days'),
            };
        };

        $featured = $upcoming->first();
        $rest = $upcoming->slice(1);

        // Скільки лишилося до найближчої події (дати в БД — київський настінний час)
        $daysLeft = $featured
            ? (int) now()->startOfDay()->diffInDays($featured->starts_at->copy()->startOfDay(), false)
            : null;

        $countdown = match (true) {
            $daysLeft === null => null,
            $daysLeft <= 0 => __('feature.today'),
            $daysLeft === 1 => __('feature.tomorrow'),
            default => __('feature.event_days', ['count' => $daysLeft, 'word' => $dayWord($daysLeft)]),
        };
    @endphp

    {{-- Розмітка Event: Google може показувати події у видачі з датою та місцем --}}
    @if ($upcoming->isNotEmpty())
        @php
            $eventsLd = $upcoming->map(fn ($e) => array_filter([
                '@context' => 'https://schema.org',
                '@type' => 'Event',
                'name' => $e->localized('title'),
                // starts_at/ends_at — київський wall-clock (Gotcha «Таймзона»)
                'startDate' => \App\Support\StructuredData::wallClockDate($e->starts_at),
                'endDate' => \App\Support\StructuredData::wallClockDate($e->ends_at),
                'description' => $e->localized('description') ?: null,
                'eventStatus' => 'https://schema.org/EventScheduled',
                'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
                'location' => [
                    '@type' => 'Place',
                    'name' => $e->localized('location') ?: \App\Support\StructuredData::siteName(),
                    'address' => \App\Support\StructuredData::postalAddress() ?? ['@type' => 'PostalAddress', 'addressLocality' => __('public.city'), 'addressCountry' => 'UA'],
                ],
                'organizer' => \App\Support\StructuredData::publisher(),
                // Власне посилання події (новина/сторінка), інакше — календар подій; лише http(s) або шлях сайту
                'url' => filled($e->url) && preg_match('~^(/|https?://)~i', $e->url) && ! str_starts_with($e->url, '//')
                    ? \App\Support\StructuredData::absoluteUrl(\App\Support\LocalizedUrl::to($e->url))
                    : \App\Support\LocalizedUrl::route('events'),
            ]))->values()->all();
        @endphp
        <script type="application/ld+json">{!! json_encode($eventsLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
    @endif

    {{-- Світла шапка розділу — у стилі новин, відео, галереї, спеціальностей і розкладу дзвінків --}}
    <section class="border-b border-slate-200/70 bg-slate-50/80">
        <div class="container-site py-8 lg:py-10">
            <x-breadcrumbs tone="light" :items="[
                ['label' => __('public.home'), 'url' => \App\Support\LocalizedUrl::route('home')],
                ['label' => __('public.events')],
            ]" />

            <div class="relative mt-4 overflow-hidden rounded-2xl bg-white px-6 py-8 shadow-sm ring-1 ring-slate-200/80 sm:px-10 sm:py-10">
                {{-- Декоративний контур календаря — заповнює порожнечу праворуч на великих екранах --}}
                <x-ico name="calendar-days" aria-hidden="true"
                       class="pointer-events-none absolute -right-8 top-1/2 hidden h-64 w-64 -translate-y-1/2 text-brand-50 lg:block" />

                <div class="relative max-w-3xl">
                    <h1 class="text-3xl font-extrabold leading-tight text-brand-950 sm:text-4xl lg:text-[2.75rem]">{{ __('public.events_title') }}</h1>
                    <div class="accent-rule"></div>
                    <p class="mt-5 text-lg leading-relaxed text-slate-500">
                        {{ __('feature.upcoming_activities_open_days_and_meetings_for') }}
                    </p>

                    @if ($upcoming->isNotEmpty() || $past->isNotEmpty())
                        <div class="mt-5 flex flex-wrap items-center gap-2 text-sm">
                            @if ($upcoming->isNotEmpty())
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-gold-50 px-3 py-1 font-semibold text-gold-700 ring-1 ring-gold-300/70">
                                    <x-ico name="calendar-days" class="h-4 w-4" aria-hidden="true" />
                                    {{ __('feature.events_ahead', ['count' => $upcoming->count(), 'word' => $eventWord($upcoming->count())]) }}
                                </span>
                            @endif
                            @if ($featured)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-50 px-3 py-1 font-semibold text-brand-800 ring-1 ring-brand-100">
                                    <x-ico name="clock" class="h-4 w-4" aria-hidden="true" />
                                    {{ __('feature.next_event_date', ['date' => $featured->starts_at->translatedFormat('j F')]) }}
                                </span>
                            @endif
                            @if ($pastCount)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-50 px-3 py-1 font-semibold text-brand-800 ring-1 ring-brand-100">
                                    <x-ico name="archive-box" class="h-4 w-4" aria-hidden="true" />
                                    {{ __('feature.events_archived', ['count' => $pastCount, 'word' => $eventWord($pastCount)]) }}
                                </span>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <section class="container-site py-12 lg:py-14">
        @if ($upcoming->isEmpty() && $past->isEmpty())
            {{-- Порожній стан: подій ще не додано — ведемо абітурієнта до новин і контактів --}}
            <div class="card mx-auto flex max-w-4xl flex-col items-center gap-8 p-8 text-center sm:p-12 lg:flex-row lg:text-left">
                <div class="grid h-28 w-28 shrink-0 place-items-center rounded-3xl bg-brand-50 text-brand-300">
                    <x-ico name="calendar-days" class="h-14 w-14" aria-hidden="true" />
                </div>
                <div>
                    <h2 class="text-2xl font-extrabold text-brand-950">{{ __('public.no_events') }}</h2>
                    <p class="mt-3 leading-relaxed text-slate-500">
                        {{ __('feature.new_events_will_appear_here_with_calendar') }}
                    </p>
                    <div class="mt-6 flex flex-wrap justify-center gap-3 lg:justify-start">
                        <a href="{{ \App\Support\LocalizedUrl::route('news.index') }}" class="btn-accent">
                            {{ __('feature.browse_news') }} <x-ico name="arrow-right" class="h-4 w-4" aria-hidden="true" />
                        </a>
                        <a href="{{ \App\Support\LocalizedUrl::route('contacts') }}" class="btn-outline">{{ __('feature.ask_the_college') }}</a>
                    </div>
                </div>
            </div>
        @endif

        @if ($upcoming->isNotEmpty())
            <div class="flex items-center gap-3">
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-gold-50 text-gold-600 ring-1 ring-gold-200">
                    <x-ico name="calendar-days" class="h-5 w-5" aria-hidden="true" />
                </span>
                <div>
                    <h2 class="text-2xl font-extrabold text-brand-950">{{ __('public.upcoming_events') }}</h2>
                    <div class="accent-rule"></div>
                </div>
            </div>

            {{-- Найближча подія — великим блоком: дата у navy, деталі та кнопки календаря поруч --}}
            <article class="card mt-8 overflow-hidden lg:grid lg:grid-cols-[18rem_1fr]">
                <div class="relative flex flex-col justify-center overflow-hidden bg-gradient-to-br from-brand-800 to-brand-950 px-6 py-6 text-white sm:px-8 sm:py-8">
                    <svg aria-hidden="true" class="pointer-events-none absolute inset-0 h-full w-full text-white/[0.07]">
                        <defs>
                            <pattern id="ev-grid" width="28" height="28" patternUnits="userSpaceOnUse">
                                <path d="M28 0H0V28" fill="none" stroke="currentColor" stroke-width="1" />
                            </pattern>
                        </defs>
                        <rect width="100%" height="100%" fill="url(#ev-grid)" />
                    </svg>

                    <div class="relative">
                        <span class="block font-display text-6xl font-extrabold leading-none sm:text-7xl">{{ $featured->starts_at->format('d') }}</span>
                        {{-- Місяць у родовому відмінку («серпня»): Carbon відмінює його лише у форматі з днем --}}
                        <span class="mt-2 block text-xl font-bold text-gold-300">{{ \Illuminate\Support\Str::after($featured->starts_at->translatedFormat('j F Y'), ' ') }}</span>
                        <span class="mt-1 block text-sm text-brand-200">{{ $featured->starts_at->translatedFormat('l') }}</span>

                        @if ($featured->ends_at && ! $featured->ends_at->isSameDay($featured->starts_at))
                            <span class="mt-3 inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1 text-xs font-semibold">
                                <x-ico name="arrow-long-right" class="h-4 w-4" aria-hidden="true" />
                                {{ __('feature.until_date', ['date' => $featured->ends_at->translatedFormat('j F')]) }}
                            </span>
                        @endif
                    </div>
                </div>

                <div class="p-6 sm:p-8 lg:p-10">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="badge bg-gold-100 text-gold-800 ring-1 ring-gold-300/70">
                            <x-ico name="star" variant="solid" class="h-3.5 w-3.5" aria-hidden="true" /> {{ __('feature.next_event') }}
                        </span>
                        @if ($countdown)
                            <span class="badge bg-brand-50 text-brand-800 ring-1 ring-brand-100">{{ $countdown }}</span>
                        @endif
                    </div>

                    <h3 class="mt-4 text-2xl font-extrabold leading-tight text-brand-950 sm:text-3xl">{{ $featured->localized('title') }}</h3>

                    <div class="mt-4 flex flex-wrap gap-x-6 gap-y-2 text-sm text-slate-600">
                        <span class="inline-flex items-center gap-2">
                            <x-ico name="clock" class="h-4 w-4 shrink-0 text-brand-500" aria-hidden="true" />
                            {{ $featured->starts_at->format('H:i') }}
                            @if ($featured->ends_at)
                                @if ($featured->ends_at->isSameDay($featured->starts_at))
                                    – {{ $featured->ends_at->format('H:i') }}
                                @else
                                    – {{ $featured->ends_at->translatedFormat('j F, H:i') }}
                                @endif
                            @endif
                        </span>
                        @if ($featured->localized('location'))
                            <span class="inline-flex items-center gap-2">
                                <x-ico name="map-pin" class="h-4 w-4 shrink-0 text-brand-500" aria-hidden="true" />
                                {{ $featured->localized('location') }}
                            </span>
                        @endif
                    </div>

                    @if ($featured->localized('description'))
                        <p class="mt-4 max-w-2xl leading-relaxed text-slate-600">{{ $featured->localized('description') }}</p>
                    @endif

                    <div class="mt-6 flex flex-wrap items-center gap-3">
                        <x-event-calendar-links :event="$featured" />
                        @if ($featured->url)
                            <a href="{{ $featured->url }}" @if (! str_starts_with($featured->url, \App\Support\LocalizedUrl::to('/'))) target="_blank" rel="noopener" @endif
                               class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand-700 transition hover:gap-2.5">
                                {{ __('public.details') }} <x-ico name="arrow-right" class="h-4 w-4" aria-hidden="true" />
                            </a>
                        @endif
                    </div>
                </div>
            </article>

            @if ($rest->isNotEmpty())
                <div class="mt-6 grid gap-6 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                    @foreach ($rest as $event)
                        <x-event-card :event="$event" />
                    @endforeach
                </div>
            @endif
        @endif

        @if ($past->isNotEmpty())
            <div class="mt-14 flex items-center gap-3">
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-slate-100 text-slate-500 ring-1 ring-slate-200">
                    <x-ico name="archive-box" class="h-5 w-5" aria-hidden="true" />
                </span>
                <h2 class="text-xl font-bold text-slate-500">{{ __('public.past_events') }}</h2>
            </div>

            <div class="mt-6 divide-y divide-slate-100 overflow-hidden rounded-2xl bg-white ring-1 ring-slate-200/80">
                @foreach ($past as $event)
                    <div class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:gap-5">
                        <div class="flex shrink-0 items-center gap-3">
                            <span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-slate-50 text-center leading-none text-slate-500 ring-1 ring-slate-200">
                                <span>
                                    <span class="block text-base font-extrabold">{{ $event->starts_at->format('d') }}</span>
                                    <span class="mt-0.5 block text-[10px] font-semibold uppercase">{{ $event->starts_at->translatedFormat('M') }}</span>
                                </span>
                            </span>
                            <span class="text-xs font-semibold uppercase tracking-wide text-slate-400 sm:hidden">{{ $event->starts_at->format('Y') }}</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-slate-700">{{ $event->localized('title') }}</p>
                            @if ($event->localized('description'))
                                <p class="mt-1 line-clamp-2 text-sm text-slate-500">{{ $event->localized('description') }}</p>
                            @endif
                        </div>
                        <span class="hidden shrink-0 text-sm tabular-nums text-slate-400 sm:block">{{ $event->starts_at->format('Y') }}</span>
                        @if ($event->url)
                            <a href="{{ $event->url }}" @if (! str_starts_with($event->url, \App\Support\LocalizedUrl::to('/'))) target="_blank" rel="noopener" @endif
                               class="inline-flex shrink-0 items-center gap-1.5 text-sm font-semibold text-brand-700 transition hover:gap-2.5">
                                {{ __('public.details') }} <x-ico name="arrow-right" class="h-4 w-4" aria-hidden="true" />
                            </a>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    {{-- Заклик: де стежити за подіями коледжу --}}
    <section class="border-t border-slate-200/70 bg-slate-50/80">
        <div class="container-site py-12 lg:py-14">
            <div class="card relative overflow-hidden bg-gradient-to-br from-brand-50 via-white to-white p-8 lg:p-12">
                <x-ico name="bell-alert" aria-hidden="true"
                       class="pointer-events-none absolute -left-10 top-1/2 hidden h-72 w-72 -translate-y-1/2 text-brand-50 lg:block" />
                <div class="relative lg:ml-64">
                    <h2 class="text-2xl font-extrabold leading-tight text-brand-950 sm:text-3xl">
                        {{ __('feature.stay_informed_about_college_events') }}
                    </h2>
                    <p class="mt-3 max-w-2xl leading-relaxed text-slate-600">
                        {{ __('feature.open_days_applicant_meetings_and_important_admission') }}
                    </p>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <a href="{{ \App\Support\LocalizedUrl::route('news.index') }}" class="btn-accent">
                            {{ __('public.news_title') }} <x-ico name="arrow-right" class="h-4 w-4" aria-hidden="true" />
                        </a>
                        <a href="{{ \App\Support\LocalizedUrl::route('contacts') }}" class="btn-outline">{{ __('feature.admissions_office_contacts') }}</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

</x-layouts.app>
