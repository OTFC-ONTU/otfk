<x-layouts.app>

    {{-- ===================== ГЕРОЙ / БАНЕР (слайдер) ===================== --}}
    <x-home.hero-slider :banners="$banners" />

    {{-- ===================== ШВИДКІ РОЗДІЛИ ===================== --}}
    @if ($tiles->isNotEmpty())
        <section class="container-site mt-14">
            <div class="text-center">
                <h2 class="text-2xl sm:text-3xl">{{ __('public.quick_sections') }}</h2>
                <div class="accent-rule mx-auto"></div>
            </div>
            <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4 lg:gap-5">
                @foreach ($tiles as $tile)
                    <a href="{{ \App\Support\LocalizedUrl::to($tile->url) }}" @if ($tile->open_new_tab) target="_blank" rel="noopener" @endif
                       class="card card-interactive group flex flex-col p-6">
                        <span class="grid h-12 w-12 place-items-center rounded-full bg-{{ $tile->color }}-50 text-{{ $tile->color }}-600 transition group-hover:bg-{{ $tile->color }}-600 group-hover:text-white">
                            <x-ico :name="$tile->icon ?: 'academic-cap'" class="h-6 w-6" />
                        </span>
                        <h3 class="mt-4 font-bold text-slate-900">{{ $tile->localized('title') }}</h3>
                        @if ($tile->localized('description'))
                            <p class="mt-1 text-sm leading-relaxed text-slate-500">{{ $tile->localized('description') }}</p>
                        @endif
                        <span class="mt-auto self-end pt-4 text-gold-600 transition group-hover:translate-x-1">
                            <x-ico name="arrow-right" class="h-5 w-5" />
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- ===================== НАЙБЛИЖЧІ ПОДІЇ ===================== --}}
    @if ($events->isNotEmpty())
        <section data-reveal class="container-site py-16">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <h2 class="text-3xl font-extrabold text-slate-900">{{ __('public.upcoming_events') }}</h2>
                    <div class="accent-rule"></div>
                </div>
                <a href="{{ \App\Support\LocalizedUrl::route('events') }}" class="btn-outline shrink-0">{{ __('public.all_events') }} <x-ico name="arrow-right" class="h-4 w-4" /></a>
            </div>
            <div class="mt-9 grid gap-5 md:grid-cols-3">
                @foreach ($events as $event)
                    <div class="card flex gap-4 p-5">
                        <div class="grid h-16 w-16 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-800">
                            <div class="text-center leading-none">
                                <div class="text-2xl font-extrabold">{{ $event->starts_at->format('d') }}</div>
                                <div class="mt-0.5 text-[11px] font-semibold uppercase">{{ $event->starts_at->translatedFormat('M') }}</div>
                            </div>
                        </div>
                        <div class="min-w-0">
                            <h3 class="font-bold leading-snug text-slate-900">{{ $event->localized('title') }}</h3>
                            <p class="mt-1 text-xs text-slate-500">
                                <x-ico name="clock" class="-mt-0.5 inline h-3.5 w-3.5" /> {{ $event->starts_at->format('H:i') }}
                                @if ($event->localized('location'))
                                    · <x-ico name="map-pin" class="-mt-0.5 inline h-3.5 w-3.5" /> {{ $event->localized('location') }}
                                @endif
                            </p>
                            @if ($event->localized('description'))
                                <p class="mt-1.5 line-clamp-2 text-sm text-slate-500">{{ $event->localized('description') }}</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- ===================== НОВИНИ ===================== --}}
    @if ($news->isNotEmpty())
        <section data-reveal class="container-site py-16">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <h2 class="text-3xl font-extrabold text-slate-900">{{ __('public.latest_news') }}</h2>
                    <div class="accent-rule"></div>
                </div>
                <a href="{{ \App\Support\LocalizedUrl::route('news.index') }}" class="btn-outline shrink-0">{{ __('public.all_news') }} <x-ico name="arrow-right" class="h-4 w-4" /></a>
            </div>
            <div class="mt-9 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($news as $item)
                    <x-news-card :item="$item" />
                @endforeach
            </div>
        </section>
    @endif

    {{-- ===================== ВІДЕО ===================== --}}
    @if ($videos->isNotEmpty())
        <section data-reveal class="bg-slate-50 py-16">
            <div class="container-site">
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <h2 class="text-3xl font-extrabold text-slate-900">{{ __('public.latest_videos') }}</h2>
                        <div class="accent-rule"></div>
                    </div>
                    <a href="{{ \App\Support\LocalizedUrl::route('video.index') }}" class="btn-outline shrink-0">{{ __('public.all_videos') }} <x-ico name="arrow-right" class="h-4 w-4" /></a>
                </div>
                <div class="mt-9 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($videos as $video)
                        <a href="{{ $video->watch_url }}" target="_blank" rel="noopener" class="card card-interactive group overflow-hidden">
                            <div class="relative aspect-video overflow-hidden bg-slate-900">
                                <img src="{{ $video->thumbnail }}" alt="{{ $video->localized('title') }}" loading="lazy" decoding="async" class="h-full w-full object-cover opacity-90 transition group-hover:scale-105 group-hover:opacity-100">
                                <span class="absolute inset-0 grid place-items-center">
                                    <span class="grid h-14 w-14 place-items-center rounded-full bg-white/90 text-brand-700 shadow-lg transition group-hover:scale-110">
                                        <x-ico name="play" variant="solid" class="h-6 w-6" />
                                    </span>
                                </span>
                            </div>
                            <div class="p-4">
                                <h3 class="line-clamp-2 font-semibold text-slate-800 group-hover:text-brand-700">{{ $video->localized('title') }}</h3>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- ===================== ЦЬОГО ДНЯ В КОЛЕДЖІ ===================== --}}
    @if ($onThisDay)
        @php
            $sameDay = $onThisDay->published_at->format('m-d') === now()->format('m-d');
            $heritageTeaser = $onThisDay->usesHeritagePresentation();
        @endphp
        <section data-reveal class="container-site mt-16">
            <a href="{{ \App\Support\LocalizedUrl::route('news.show', $onThisDay) }}"
               @class([
                   'group mx-auto flex max-w-3xl items-center gap-4 overflow-hidden p-4 sm:gap-5',
                   'heritage-teaser' => $heritageTeaser,
                   'card card-interactive' => ! $heritageTeaser,
               ])>
                @if ($onThisDay->cover_image)
                    <x-picture :path="$onThisDay->cover_image" :alt="$onThisDay->localized('title')" loading="lazy" decoding="async"
                               @class([
                                   'h-16 w-24 shrink-0 object-cover sm:h-20 sm:w-28',
                                   'rounded-lg ring-2 ring-gold-300/50 sepia-[0.12]' => $heritageTeaser,
                                   'rounded-xl' => ! $heritageTeaser,
                               ]) />
                @else
                    <span @class([
                        'grid h-16 w-24 shrink-0 place-items-center sm:h-20 sm:w-28',
                        'rounded-lg bg-gold-100/80 text-gold-600 ring-2 ring-gold-300/50' => $heritageTeaser,
                        'rounded-xl bg-gradient-to-br from-brand-700 to-brand-900 text-white/30' => ! $heritageTeaser,
                    ])>
                        <x-ico :name="$heritageTeaser ? 'archive-box' : 'clock'" class="h-8 w-8" />
                    </span>
                @endif
                <div class="min-w-0 flex-1">
                    <p @class([
                        'inline-flex flex-wrap items-center gap-1.5 text-xs font-semibold uppercase tracking-wide',
                        'font-heritage-display normal-case tracking-normal text-brand-800/90' => $heritageTeaser,
                        'text-gold-600' => ! $heritageTeaser,
                    ])>
                        <x-ico name="sparkles" class="h-3.5 w-3.5" />
                        {{ __('feature.archive_year', ['label' => $sameDay ? __('feature.on_this_day') : __('feature.around_this_day'), 'year' => $onThisDay->published_at->year]) }}
                        @if ($heritageTeaser)
                            <span class="rounded-full bg-gold-200/60 px-2 py-0.5 text-[10px] normal-case tracking-normal text-gold-900 ring-1 ring-gold-400/50">{{ __('public.archive') }}</span>
                        @endif
                    </p>
                    <h3 @class([
                        'mt-1 line-clamp-2 font-bold transition',
                        'font-heritage-display text-lg font-semibold text-brand-950 group-hover:text-brand-800' => $heritageTeaser,
                        'text-slate-900 group-hover:text-brand-700' => ! $heritageTeaser,
                    ])>{{ $onThisDay->localized('title') }}</h3>
                    <p @class([
                        'mt-0.5 text-xs',
                        'font-heritage-body italic text-brand-800/70' => $heritageTeaser,
                        'text-slate-400' => ! $heritageTeaser,
                    ])>{{ __('feature.archive_date', ['date' => $onThisDay->published_at->translatedFormat('j F Y')]) }}</p>
                </div>
                <x-ico name="arrow-right" @class([
                    'h-5 w-5 shrink-0 transition group-hover:translate-x-1',
                    'text-gold-500 group-hover:text-gold-700' => $heritageTeaser,
                    'text-slate-300 group-hover:text-brand-600' => ! $heritageTeaser,
                ]) />
            </a>
        </section>
    @endif

    {{-- ===================== КОЛЕДЖ У ЦИФРАХ ===================== --}}
    @if ($stats->isNotEmpty())
        <section data-reveal class="mt-16 bg-brand-950">
            <div class="container-site grid grid-cols-2 gap-x-6 gap-y-10 py-14 text-center lg:grid-cols-4 lg:py-16">
                @foreach ($stats as $stat)
                    @php
                        // «1000+» → «1 000+» (розряди), решта значень — як є; без анімації
                        preg_match('/^([^\d]*)(\d[\d\s]*)(.*)$/u', $stat->value, $m);
                        $num = isset($m[2]) ? (int) str_replace(' ', '', $m[2]) : null;
                    @endphp
                    <div>
                        @if ($stat->icon)
                            <x-ico :name="$stat->icon" class="mx-auto mb-3 h-8 w-8 text-gold-400/80" />
                        @endif
                        <div class="font-display text-4xl font-extrabold tracking-tight text-white sm:text-5xl">
                            @if ($num !== null)
                                {{ $m[1] }}{{ number_format($num, 0, ',', ' ') }}{{ $m[3] }}
                            @else
                                {{ $stat->value }}
                            @endif
                        </div>
                        <div class="mt-2 text-sm font-medium uppercase tracking-wide text-brand-200">{{ $stat->localized('label') }}</div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

</x-layouts.app>
