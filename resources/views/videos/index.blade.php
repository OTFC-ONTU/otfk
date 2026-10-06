<x-layouts.app title="{{ __('public.videos') }}" description="{{ __('feature.college_videos_events_creative_projects_and_student') }}">

    @php
        $s = \App\Models\Setting::publicMap();
        $youtubeChannel = trim($s['social_youtube'] ?? '');

        // Головне відео — лише на першій сторінці, коли є що показати нижче
        $items = $videos->getCollection();
        $featured = ($videos->onFirstPage() && $items->count() > 1) ? $items->first() : null;
        $rest = $featured ? $items->slice(1) : $items;
    @endphp

    {{-- Світла шапка розділу — у стилі детальної новини --}}
    <section class="border-b border-slate-200/70 bg-slate-50/80">
        <div class="container-site py-8 lg:py-10">
            <x-breadcrumbs tone="light" :items="[
                ['label' => __('public.home'), 'url' => \App\Support\LocalizedUrl::route('home')],
                ['label' => __('public.videos')],
            ]" />

            <div class="relative mt-4 overflow-hidden rounded-2xl bg-white px-6 py-8 shadow-sm ring-1 ring-slate-200/80 sm:px-10 sm:py-10">
                {{-- Декоративний контур плеєра — заповнює порожнечу праворуч на великих екранах --}}
                <x-ico name="play-circle" aria-hidden="true"
                       class="pointer-events-none absolute -right-8 top-1/2 hidden h-64 w-64 -translate-y-1/2 text-brand-50 lg:block" />

                <div class="relative max-w-3xl">
                    <h1 class="text-3xl font-extrabold leading-tight text-brand-950 sm:text-4xl lg:text-[2.75rem]">{{ __('public.video_materials') }}</h1>
                    <div class="accent-rule"></div>
                    <p class="mt-5 text-lg leading-relaxed text-slate-500">
                        {{ __('feature.videos_of_college_events_creative_projects_and') }}
                    </p>
                    @if ($videos->total() > 0)
                        <p class="mt-4 inline-flex items-center gap-1.5 rounded-full bg-brand-50 px-3 py-1 text-sm font-semibold text-brand-700 ring-1 ring-brand-200/70">
                            <x-brand-ico name="youtube" class="h-4 w-4" />
                            {{ __('feature.videos_count', ['count' => $videos->total()]) }}
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </section>

    @if ($videos->isNotEmpty())
        {{-- Уся сітка живе в одному Alpine-стані: клік по картці відкриває плеєр у лайтбоксі --}}
        <div x-data="{
                 src: null, title: '',
                 open(src, title) { this.src = src; this.title = title },
                 close() { this.src = null; this.title = '' },
                 init() {
                     window.addEventListener('keydown', e => { if (e.key === 'Escape') this.close() });
                 },
             }"
             x-effect="document.body.style.overflow = src === null ? '' : 'hidden'">

            <section class="container-site py-12">
                @if ($featured)
                    {{-- Головне відео: велика обкладинка ліворуч, опис і кнопки праворуч --}}
                    <article class="card group mb-10 grid overflow-hidden lg:grid-cols-2">
                        <button type="button" @click="open(@js($featured->private_embed_url), @js($featured->localized('title')))"
                                aria-label="{{ __('feature.play_video', ['title' => $featured->localized('title')]) }}"
                                class="relative block aspect-video overflow-hidden bg-slate-900 text-left">
                            <img src="{{ $featured->thumbnail }}" alt="{{ $featured->localized('title') }}" loading="eager" decoding="async"
                                 class="h-full w-full object-cover opacity-90 transition duration-500 group-hover:scale-105 group-hover:opacity-100">
                            <span class="absolute inset-0 grid place-items-center">
                                <span class="grid h-20 w-20 place-items-center rounded-full bg-gold-500 text-slate-900 shadow-lg transition group-hover:scale-110">
                                    <x-ico name="play" variant="solid" class="h-8 w-8" />
                                </span>
                            </span>
                        </button>

                        <div class="flex flex-col justify-center p-6 lg:p-10">
                            <span class="badge w-fit bg-brand-50 text-brand-700 ring-1 ring-brand-200/70">{{ __('feature.watch_now') }}</span>
                            <h2 class="mt-3 text-2xl font-extrabold leading-tight text-slate-900 lg:text-3xl">{{ $featured->localized('title') }}</h2>
                            <div class="mt-4 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm text-slate-500">
                                @if ($featured->published_at)
                                    <span class="inline-flex items-center gap-1.5">
                                        <x-ico name="calendar-days" class="h-4 w-4 text-brand-400" />
                                        {{ $featured->published_at->translatedFormat('j F Y') }}
                                    </span>
                                @endif
                                <span class="inline-flex items-center gap-1.5 text-[#ff0000]">
                                    <x-brand-ico name="youtube" class="h-4 w-4" /> <span class="text-slate-500">YouTube</span>
                                </span>
                            </div>
                            @if (filled($featured->localized('description')))
                                <p class="mt-5 line-clamp-4 leading-relaxed text-slate-500">{{ $featured->localized('description') }}</p>
                            @endif
                            <div class="mt-7 flex flex-wrap items-center gap-3">
                                <button type="button" class="btn-accent" @click="open(@js($featured->private_embed_url), @js($featured->localized('title')))">
                                    <x-ico name="play" variant="solid" class="h-4 w-4" /> {{ __('feature.watch_video') }}
                                </button>
                                <a href="{{ $featured->watch_url }}" target="_blank" rel="noopener" class="btn-outline">
                                    {{ __('feature.open_on_youtube') }} <x-ico name="arrow-top-right-on-square" class="h-4 w-4" />
                                </a>
                            </div>
                        </div>
                    </article>
                @endif

                @if ($rest->isNotEmpty())
                    <h2 class="text-xl font-bold text-slate-900 sm:text-2xl">{{ $featured ? __('public.all_videos') : __('public.videos') }}</h2>
                    <div class="accent-rule"></div>

                    <div class="mt-7 grid gap-6 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                        @foreach ($rest as $video)
                            <article class="card card-interactive group flex flex-col overflow-hidden">
                                <button type="button" @click="open(@js($video->private_embed_url), @js($video->localized('title')))"
                                        aria-label="{{ __('feature.play_video', ['title' => $video->localized('title')]) }}"
                                        class="relative block aspect-video overflow-hidden bg-slate-900 text-left">
                                    <img src="{{ $video->thumbnail }}" alt="{{ $video->localized('title') }}" loading="lazy" decoding="async"
                                         class="h-full w-full object-cover opacity-90 transition duration-500 group-hover:scale-105 group-hover:opacity-100">
                                    <span class="absolute inset-0 grid place-items-center">
                                        <span class="grid h-14 w-14 place-items-center rounded-full bg-white/90 text-brand-700 shadow-lg transition group-hover:scale-110 group-hover:bg-gold-500 group-hover:text-slate-900">
                                            <x-ico name="play" variant="solid" class="h-6 w-6" />
                                        </span>
                                    </span>
                                </button>

                                <div class="flex flex-1 flex-col p-5">
                                    <h3 class="line-clamp-2 text-base font-bold leading-snug text-slate-900 transition group-hover:text-brand-700">{{ $video->localized('title') }}</h3>
                                    <div class="mt-auto flex items-center justify-between gap-3 pt-4 text-xs text-slate-400">
                                        @if ($video->published_at)
                                            <span class="inline-flex items-center gap-1.5">
                                                <x-ico name="calendar-days" class="h-3.5 w-3.5" />
                                                {{ $video->published_at->translatedFormat('j F Y') }}
                                            </span>
                                        @else
                                            <span></span>
                                        @endif
                                        <a href="{{ $video->watch_url }}" target="_blank" rel="noopener"
                                           title="{{ __('feature.open_on_youtube') }}" aria-label="{{ __('feature.youtube_video', ['title' => $video->localized('title')]) }}"
                                           class="shrink-0 transition hover:text-brand-700">
                                            <x-ico name="arrow-top-right-on-square" class="h-4 w-4" />
                                        </a>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif

                @if ($videos->hasPages())
                    <div class="mt-10">{{ $videos->onEachSide(1)->links() }}</div>
                @endif
            </section>

            {{-- Лайтбокс з плеєром (youtube-nocookie — без трекінгових cookie до натискання) --}}
            <div x-show="src !== null" x-cloak @click.self="close()"
                 role="dialog" aria-modal="true" aria-label="{{ __('feature.video_player') }}"
                 class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-950/90 p-4 backdrop-blur-sm">
                <button type="button" @click="close()" aria-label="{{ __('public.close') }}"
                        class="absolute right-4 top-4 grid h-11 w-11 place-items-center rounded-full bg-white/10 text-white transition hover:bg-white/20">
                    <x-ico name="x-mark" class="h-6 w-6" />
                </button>

                <div class="w-full max-w-5xl" @click.stop>
                    <template x-if="src !== null">
                        <div class="aspect-video w-full overflow-hidden rounded-xl bg-black shadow-2xl">
                            <iframe class="h-full w-full" :src="src + '?autoplay=1&rel=0'"
                                    :title="title" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                    referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
                        </div>
                    </template>
                    <p class="mt-3 text-center text-sm text-white/80" x-text="title"></p>
                </div>
            </div>
        </div>
    @else
        <section class="container-site py-12">
            <x-empty-state icon="play-circle" title="{{ __('public.no_videos') }}" />
        </section>
    @endif

    {{-- Заклик до соцмереж: показуємо лише ті, що заповнені в налаштуваннях --}}
    @if ($youtubeChannel || ! empty($s['social_facebook']) || ! empty($s['social_instagram']))
        <section class="bg-brand-950">
            <div class="container-site flex flex-col items-start gap-6 py-12 lg:flex-row lg:items-center lg:justify-between lg:py-14">
                <div class="max-w-2xl">
                    <h2 class="text-2xl font-extrabold text-white sm:text-3xl">{{ __('feature.more_videos_on_our_social_media') }}</h2>
                    <div class="accent-rule"></div>
                    <p class="mt-4 text-brand-100">
                        {{ __('feature.follow_our_updates_on_events_creative_projects') }}
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    @if ($youtubeChannel)
                        <a href="{{ $youtubeChannel }}" target="_blank" rel="noopener" class="btn-accent">
                            <x-brand-ico name="youtube" class="h-4 w-4" /> {{ __('feature.visit_our_youtube_channel') }}
                        </a>
                    @endif
                    @if (! empty($s['social_facebook']))
                        <a href="{{ $s['social_facebook'] }}" target="_blank" rel="noopener" aria-label="{{ __('feature.college_facebook') }}"
                           class="inline-flex items-center gap-2 rounded-lg bg-white/10 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-white/20">
                            <x-brand-ico name="facebook" class="h-4 w-4" /> Facebook
                        </a>
                    @endif
                    @if (! empty($s['social_instagram']))
                        <a href="{{ $s['social_instagram'] }}" target="_blank" rel="noopener" aria-label="{{ __('feature.college_instagram') }}"
                           class="inline-flex items-center gap-2 rounded-lg bg-white/10 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-white/20">
                            <x-brand-ico name="instagram" class="h-4 w-4" /> Instagram
                        </a>
                    @endif
                </div>
            </div>
        </section>
    @endif

</x-layouts.app>
