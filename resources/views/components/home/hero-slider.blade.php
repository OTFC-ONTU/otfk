@props(['banners'])

@php
    use App\Support\BannerOverlay;

    $slides = $banners->values();
    $count = $slides->count();
    /* Чипи-факти в героя — топ-3 з «Коледж у цифрах» (адмінка → Показники) */
    $heroStats = \App\Models\StatItem::active()->limit(3)->get();
@endphp

@if ($count > 0)
    <section class="relative overflow-hidden bg-brand-950"
             @if ($count > 1)
                 x-data="{
                     index: 0,
                     total: {{ $count }},
                     timer: null,
                     touchX: null,
                     reduced: window.matchMedia('(prefers-reduced-motion: reduce)').matches,
                     start() {
                         if (this.reduced || this.total < 2) return;
                         this.stop();
                         this.timer = setInterval(() => this.next(), 6000);
                     },
                     stop() { if (this.timer) { clearInterval(this.timer); this.timer = null; } },
                     next() { this.index = (this.index + 1) % this.total; },
                     prev() { this.index = (this.index - 1 + this.total) % this.total; },
                     go(i) { this.index = i; },
                     swipeStart(e) { this.touchX = e.changedTouches[0].clientX; },
                     swipeEnd(e) {
                         if (this.touchX === null) return;
                         const dx = e.changedTouches[0].clientX - this.touchX;
                         this.touchX = null;
                         if (Math.abs(dx) < 40) return;
                         dx < 0 ? this.next() : this.prev();
                         this.start();
                     },
                 }"
                 x-init="start()"
                 @pointerenter="$event.pointerType === 'mouse' && stop()"
                 @pointerleave="$event.pointerType === 'mouse' && start()"
                 @touchstart.passive="swipeStart($event)"
                 @touchend.passive="swipeEnd($event)"
                 @focusin="stop()"
                 @focusout="start()"
                 role="region"
                 aria-roledescription="carousel"
                 aria-label="{{ __('public.main_banner') }}"
             @endif>
        {{-- Єдиний H1 головної — назва коледжу; заголовки слайдів нижче — H2, бо банерів може бути кілька --}}
        <h1 class="sr-only">{{ \App\Models\Setting::publicGet('brand_name') ?: __('public.college_name') }}</h1>
        {{-- Сцена слайдів: власна висота, бо слайди накладаються через absolute --}}
        <div class="relative @if ($count > 1) min-h-[460px] lg:min-h-[560px] 2xl:min-h-[640px] @endif">
        @foreach ($slides as $i => $banner)
            <div @if ($count > 1)
                     x-show="index === {{ $i }}"
                     x-transition:enter="transition ease-in-out duration-700"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     x-transition:leave="transition ease-in-out duration-700"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0"
                     :aria-hidden="index !== {{ $i }}"
                 @endif
                 class="@if ($count > 1) absolute inset-0 @endif flex min-h-[460px] flex-col justify-center lg:min-h-[560px] 2xl:min-h-[640px]">
                <div class="pointer-events-none absolute inset-0 overflow-hidden">
                    @if ($banner->image)
                        @if ($i === 0)
                            <x-picture :path="$banner->image" :alt="$banner->imageAlt()" class="h-full w-full object-cover" sized fetchpriority="high" decoding="async" />
                        @else
                            <x-picture :path="$banner->image" :alt="$banner->imageAlt()" class="h-full w-full object-cover" loading="lazy" decoding="async" />
                        @endif
                        @if (BannerOverlay::hasOverlay())
                            <div class="absolute inset-0" style="{{ BannerOverlay::gradientStyle() }}"></div>
                            <div class="absolute inset-0" style="{{ BannerOverlay::flatStyle() }}"></div>
                        @endif
                    @else
                        <div class="h-full w-full bg-gradient-to-br from-brand-800 via-brand-900 to-brand-950"></div>
                        <div class="absolute -right-24 -top-24 h-96 w-96 rounded-full bg-brand-600/30 blur-3xl"></div>
                        <div class="absolute bottom-0 left-1/4 h-72 w-72 rounded-full bg-gold-500/10 blur-3xl"></div>
                    @endif
                </div>
                {{-- Коли слайдів кілька, знизу лишаємо місце під крапки-перемикачі, інакше вони лягають на чипи --}}
                <div class="container-site relative flex min-h-[460px] flex-col justify-center py-16 sm:py-20 lg:min-h-[560px] lg:py-28 2xl:min-h-[640px] @if ($count > 1) pb-24 sm:pb-36 lg:pb-32 @endif">
                    <div class="max-w-2xl xl:max-w-3xl">
                        @if ($banner->localized('title'))
                            {{-- Числа в заголовку («Вступ 2026») підсвічуємо золотом; e() екранує до вставки span --}}
                            <h2 class="text-[2rem] font-extrabold leading-[1.1] text-white sm:text-5xl lg:text-6xl 2xl:text-7xl">{!! preg_replace('/\d+/', '<span class="text-gold-400">$0</span>', e($banner->localized('title'))) !!}</h2>
                        @endif
                        @if ($banner->localized('subtitle'))
                            <p class="mt-4 max-w-xl leading-relaxed text-brand-100 sm:mt-5 sm:text-lg xl:text-xl">{{ $banner->localized('subtitle') }}</p>
                        @endif
                        <div class="mt-7 flex flex-wrap items-center gap-3 sm:mt-8">
                            @if ($banner->link_url)
                                <a href="{{ \App\Support\LocalizedUrl::to($banner->link_url) }}" class="btn-accent max-sm:w-full lg:px-6 lg:py-3 lg:text-base">{{ $banner->localized('link_label') ?: __('public.details') }} <x-ico name="arrow-right" class="h-4 w-4" /></a>
                            @endif
                            <a href="{{ \App\Support\LocalizedUrl::route('specialties.index') }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-white/10 px-5 py-2.5 text-sm font-semibold text-white ring-1 ring-white/25 backdrop-blur transition hover:bg-white/20 max-sm:w-full lg:px-6 lg:py-3 lg:text-base">{{ __('public.specialties') }} <x-ico name="arrow-right" class="h-4 w-4" /></a>
                        </div>
                        @if ($heroStats->isNotEmpty())
                            {{-- Чипи ховаємо на мобільних: слайди absolute у секції з фіксованою min-h, високий контент переповнює її.
                                 Замість них під сценою слайдів іде окрема стрічка фактів (sm:hidden). --}}
                            <div class="mt-10 hidden flex-wrap gap-3 sm:flex">
                                @foreach ($heroStats as $stat)
                                    <div class="flex items-center gap-3 rounded-xl bg-brand-950/55 px-4 py-2.5 ring-1 ring-white/15 backdrop-blur">
                                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-gold-500/15 text-gold-400">
                                            <x-ico :name="$stat->icon ?: 'academic-cap'" class="h-5 w-5" />
                                        </span>
                                        <span class="leading-tight">
                                            <span class="block text-base font-bold text-white">{{ $stat->value }}</span>
                                            <span class="block text-xs text-brand-100">{{ $stat->localized('label') }}</span>
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach

        @if ($count > 1)
            <div class="pointer-events-none absolute inset-x-0 bottom-4 z-10 flex items-center justify-center gap-2 sm:bottom-6 sm:gap-3">
                <button type="button" @click="prev(); start()" class="pointer-events-auto grid h-11 w-11 place-items-center rounded-full bg-white/10 text-white ring-1 ring-white/20 backdrop-blur transition hover:bg-white/20 focus:outline-none focus-visible:ring-2 focus-visible:ring-white" aria-label="{{ __('public.previous_slide') }}">
                    <x-ico name="chevron-left" class="h-5 w-5" />
                </button>
                <div class="flex" role="tablist" aria-label="{{ __('public.banner_slides') }}">
                    @foreach ($slides as $i => $banner)
                        {{-- Точка мала (10px), але тач-мішень — 44×32, інакше на телефоні в неї не влучити --}}
                        <button type="button" role="tab"
                                @click="go({{ $i }}); start()"
                                :aria-selected="index === {{ $i }}"
                                class="pointer-events-auto grid h-11 w-8 place-items-center focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
                                aria-label="{{ __('feature.slide_number', ['number' => $i + 1]) }}">
                            <span class="h-2.5 w-2.5 rounded-full transition"
                                  :class="index === {{ $i }} ? 'bg-gold-400 scale-110' : 'bg-white/40 hover:bg-white/60'"></span>
                        </button>
                    @endforeach
                </div>
                <button type="button" @click="next(); start()" class="pointer-events-auto grid h-11 w-11 place-items-center rounded-full bg-white/10 text-white ring-1 ring-white/20 backdrop-blur transition hover:bg-white/20 focus:outline-none focus-visible:ring-2 focus-visible:ring-white" aria-label="{{ __('public.next_slide') }}">
                    <x-ico name="chevron-right" class="h-5 w-5" />
                </button>
            </div>
        @endif
        </div>

        @if ($heroStats->isNotEmpty())
            {{-- Мобільна стрічка фактів: те саме, що чипи на десктопі, але поза сценою слайдів --}}
            <div class="border-t border-white/10 sm:hidden">
                <div class="container-site flex divide-x divide-white/10 py-4">
                    @foreach ($heroStats as $stat)
                        <div class="flex-1 px-2 text-center">
                            <x-ico :name="$stat->icon ?: 'academic-cap'" class="mx-auto h-5 w-5 text-gold-400/80" />
                            <span class="mt-1.5 block text-lg font-bold leading-none text-white">{{ $stat->value }}</span>
                            <span class="mt-1 block text-[11px] leading-tight text-brand-200">{{ $stat->localized('label') }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </section>
@else
    {{-- Запасний герой (поки немає банерів) --}}
    <section class="relative overflow-hidden bg-brand-950">
        <div class="pointer-events-none absolute inset-0">
            <div class="absolute -right-24 -top-24 h-96 w-96 rounded-full bg-brand-600/30 blur-3xl"></div>
            <div class="absolute bottom-0 left-1/4 h-72 w-72 rounded-full bg-gold-500/10 blur-3xl"></div>
        </div>
        <div class="container-site relative py-20 lg:py-28">
            <span class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3.5 py-1.5 text-xs font-medium text-brand-100 ring-1 ring-white/15">
                <span class="h-1.5 w-1.5 rounded-full bg-gold-400"></span> {{ __('public.university_unit') }}
            </span>
            <h1 class="mt-6 max-w-3xl text-4xl font-extrabold leading-[1.1] text-white sm:text-5xl lg:text-6xl">
                {{ __('public.college_name') }}
            </h1>
            <p class="mt-6 max-w-xl text-lg leading-relaxed text-brand-100">
                {{ __('feature.modern_professional_prehigher_education_technical_specialties_experienced') }}
            </p>
            <div class="mt-9 flex flex-wrap gap-3">
                <a href="{{ \App\Support\LocalizedUrl::to('/abituriyentu') }}" class="btn-accent">{{ __('public.applicants') }} <x-ico name="arrow-right" class="h-4 w-4" /></a>
                <a href="{{ \App\Support\LocalizedUrl::route('specialties.index') }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-white/10 px-5 py-2.5 text-sm font-semibold text-white ring-1 ring-white/20 backdrop-blur transition hover:bg-white/15">{{ __('public.specialties') }}</a>
                <a href="{{ \App\Support\LocalizedUrl::route('news.index') }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-white/10 px-5 py-2.5 text-sm font-semibold text-white ring-1 ring-white/20 backdrop-blur transition hover:bg-white/15">{{ __('public.news_title') }}</a>
            </div>
        </div>
    </section>
@endif
