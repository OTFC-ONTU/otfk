@props(['banners'])

@php
    use App\Support\BannerOverlay;

    $slides = $banners->values();
    $count = $slides->count();
@endphp

@if ($count > 0)
    <section class="relative overflow-hidden bg-brand-950 @if ($count > 1) min-h-[460px] @endif"
             @if ($count > 1)
                 x-data="{
                     index: 0,
                     total: {{ $count }},
                     timer: null,
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
                 }"
                 x-init="start()"
                 @mouseenter="stop()"
                 @mouseleave="start()"
                 @focusin="stop()"
                 @focusout="start()"
                 role="region"
                 aria-roledescription="carousel"
                 aria-label="{{ __('public.main_banner') }}"
             @endif>
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
                 class="@if ($count > 1) absolute inset-0 @endif flex min-h-[460px] flex-col justify-center">
                <div class="pointer-events-none absolute inset-0 overflow-hidden">
                    @if ($banner->image)
                        @if ($i === 0)
                            <x-picture :path="$banner->image" :alt="$banner->imageAlt()" class="h-full w-full object-cover" fetchpriority="high" decoding="async" />
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
                <div class="container-site relative flex min-h-[460px] flex-col justify-center py-20 lg:py-28">
                    <div class="max-w-2xl">
                        @if ($banner->localized('title'))
                            <h1 class="text-4xl font-extrabold leading-[1.1] text-white sm:text-5xl lg:text-6xl">{{ $banner->localized('title') }}</h1>
                        @endif
                        @if ($banner->localized('subtitle'))
                            <p class="mt-5 max-w-xl text-lg leading-relaxed text-brand-100">{{ $banner->localized('subtitle') }}</p>
                        @endif
                        @if ($banner->link_url)
                            <a href="{{ \App\Support\LocalizedUrl::to($banner->link_url) }}" class="btn-accent mt-8">{{ $banner->localized('link_label') ?: __('public.details') }} <x-ico name="arrow-right" class="h-4 w-4" /></a>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach

        @if ($count > 1)
            <div class="pointer-events-none absolute inset-x-0 bottom-6 z-10 flex items-center justify-center gap-3">
                <button type="button" @click="prev()" class="pointer-events-auto rounded-full bg-white/10 p-2 text-white ring-1 ring-white/20 backdrop-blur transition hover:bg-white/20 focus:outline-none focus-visible:ring-2 focus-visible:ring-white" aria-label="{{ __('public.previous_slide') }}">
                    <x-ico name="chevron-left" class="h-5 w-5" />
                </button>
                <div class="flex gap-2" role="tablist" aria-label="{{ __('public.banner_slides') }}">
                    @foreach ($slides as $i => $banner)
                        <button type="button" role="tab"
                                @click="go({{ $i }})"
                                :aria-selected="index === {{ $i }}"
                                class="pointer-events-auto h-2.5 w-2.5 rounded-full transition focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
                                :class="index === {{ $i }} ? 'bg-gold-400 scale-110' : 'bg-white/40 hover:bg-white/60'"
                                aria-label="{{ __('public.slide', ['number' => $i + 1]) }}"></button>
                    @endforeach
                </div>
                <button type="button" @click="next()" class="pointer-events-auto rounded-full bg-white/10 p-2 text-white ring-1 ring-white/20 backdrop-blur transition hover:bg-white/20 focus:outline-none focus-visible:ring-2 focus-visible:ring-white" aria-label="{{ __('public.next_slide') }}">
                    <x-ico name="chevron-right" class="h-5 w-5" />
                </button>
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
                {{ __('public.hero_intro') }}
            </p>
            <div class="mt-9 flex flex-wrap gap-3">
                <a href="{{ \App\Support\LocalizedUrl::to('/abituriyentu') }}" class="btn-accent">{{ __('public.applicants') }} <x-ico name="arrow-right" class="h-4 w-4" /></a>
                <a href="{{ \App\Support\LocalizedUrl::route('news.index') }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-white/10 px-5 py-2.5 text-sm font-semibold text-white ring-1 ring-white/20 backdrop-blur transition hover:bg-white/15">{{ __('public.news_title') }}</a>
            </div>
        </div>
    </section>
@endif