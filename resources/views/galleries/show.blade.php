<x-layouts.app :title="$gallery->albumLocalized('title')" :description="$gallery->albumLocalized('description')">

    @php
        // Дані для лайтбокса — один масив, щоб гортати фото стрілками
        $photoData = $gallery->photos->map(fn ($photo) => [
            'src' => $photo->url,
            'caption' => $gallery->publicCaption($photo),
        ])->values();
        $photoCount = $gallery->photos->count();
    @endphp

    {{-- Уся сторінка живе в одному Alpine-стані: кнопка в шапці, сітка й лайтбокс гортаються разом --}}
    <div x-data="{
             i: null,
             photos: @js($photoData),
             open(n) { this.i = n },
             close() { this.i = null },
             next() { this.i = (this.i + 1) % this.photos.length },
             prev() { this.i = (this.i - 1 + this.photos.length) % this.photos.length },
             init() {
                 window.addEventListener('keydown', e => {
                     if (this.i === null) return;
                     if (e.key === 'Escape') this.close();
                     if (e.key === 'ArrowRight') this.next();
                     if (e.key === 'ArrowLeft') this.prev();
                 });
             },
         }"
         x-effect="document.body.style.overflow = i === null ? '' : 'hidden'">

    {{-- Світла шапка альбому — у стилі решти оновлених розділів --}}
    <section class="border-b border-slate-200/70 bg-slate-50/80">
        <div class="container-site py-8 lg:py-10">
            <x-breadcrumbs tone="light" :items="[
                ['label' => __('public.home'), 'url' => \App\Support\LocalizedUrl::route('home')],
                ['label' => __('public.gallery'), 'url' => \App\Support\LocalizedUrl::route('galleries.index')],
                ['label' => $gallery->albumLocalized('title')],
            ]" />

            <div @class([
                'relative mt-4 overflow-hidden rounded-2xl px-6 py-8 shadow-sm ring-1 sm:px-10 sm:py-10',
                'bg-[var(--color-parchment)] ring-gold-200/70' => $gallery->is_archive,
                'bg-white ring-slate-200/80' => ! $gallery->is_archive,
            ])>
                {{-- Декоративний контур фото — заповнює порожнечу праворуч на великих екранах --}}
                <x-ico name="photo" aria-hidden="true"
                       class="pointer-events-none absolute -right-8 top-1/2 hidden h-64 w-64 -translate-y-1/2 text-brand-50 lg:block" />

                <div class="relative max-w-3xl">
                    @if ($gallery->is_archive)
                        <p class="badge mb-4 bg-gold-100 text-gold-800 ring-1 ring-gold-200">
                            <x-ico name="archive-box" class="h-4 w-4" /> {{ __('public.archive_album') }}
                        </p>
                    @endif

                    <h1 class="text-3xl font-extrabold leading-tight text-brand-950 sm:text-4xl lg:text-[2.75rem]">{{ $gallery->albumLocalized('title') }}</h1>
                    <div class="accent-rule"></div>

                    @if (filled($gallery->albumLocalized('description')))
                        <p class="mt-5 text-lg leading-relaxed text-slate-500">{{ $gallery->albumLocalized('description') }}</p>
                    @endif

                    <div class="mt-5 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm text-slate-500">
                        @if ($gallery->published_at)
                            <span class="inline-flex items-center gap-1.5">
                                <x-ico name="calendar-days" class="h-4 w-4 text-brand-400" />
                                {{ $gallery->published_at->translatedFormat('j F Y') }}
                            </span>
                        @endif
                        <span class="inline-flex items-center gap-1.5">
                            <x-ico name="photo" class="h-4 w-4 text-brand-400" />
                            {{ __('feature.photos_count', ['count' => $photoCount]) }}
                        </span>
                    </div>

                    @if ($photoCount)
                        <div class="mt-7">
                            <button type="button" @click="open(0)" class="btn-accent">
                                <x-ico name="play" variant="solid" class="h-4 w-4" /> {{ __('feature.start_viewing') }}
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>

    @if ($photoCount)
        <section @class(['container-site py-12', 'photo-archive' => $gallery->is_archive])>
            <div class="photo-archive-grid grid auto-rows-[10rem] grid-flow-dense grid-cols-2 gap-3 sm:auto-rows-[12rem] sm:grid-cols-3 lg:grid-cols-4 2xl:grid-cols-6">
                        @foreach ($gallery->photos as $index => $photo)
                            <button type="button" @click="open({{ $index }})"
                                    aria-label="{{ __('feature.open_photo', ['label' => $gallery->publicCaption($photo) ?: ($index + 1)]) }}"
                                    @class([
                                        'group relative overflow-hidden bg-slate-100 text-left',
                                        // Кожне п'яте фото — велика плитка: сітка не виглядає одноманітною
                                        'sm:col-span-2 sm:row-span-2' => $index % 5 === 0,
                                        'rounded-sm ring-2 ring-gold-300/60 shadow-[inset_0_0_24px_rgb(30_35_63/0.12)]' => $gallery->is_archive,
                                        'rounded-xl' => ! $gallery->is_archive,
                                    ])>
                                <x-picture :path="$photo->image" :alt="$gallery->publicCaption($photo) ?: $gallery->albumLocalized('title')" loading="lazy"
                                           @class([
                                               'h-full w-full object-cover transition duration-500 group-hover:scale-105',
                                               'sepia-[0.18] contrast-[1.03]' => $gallery->is_archive,
                                           ]) />

                                {{-- Підпис проступає з градієнта — читабельний на будь-якому фото --}}
                                @if ($gallery->publicCaption($photo))
                                    <span class="pointer-events-none absolute inset-x-0 bottom-0 bg-gradient-to-t from-brand-950/85 to-transparent p-3 pt-8">
                                        <span class="line-clamp-2 text-xs font-semibold leading-snug text-white">{{ $gallery->publicCaption($photo) }}</span>
                                    </span>
                                @endif

                                <span class="pointer-events-none absolute right-2 top-2 grid h-8 w-8 place-items-center rounded-full bg-white/85 text-brand-800 opacity-0 shadow-sm transition group-hover:opacity-100 group-focus-visible:opacity-100">
                                    <x-ico name="magnifying-glass-plus" class="h-4 w-4" />
                                </span>
                            </button>
                        @endforeach
            </div>
        </section>

        {{-- Лайтбокс: фото ліворуч, підпис праворуч, гортання стрілками --}}
        <div x-show="i !== null" x-cloak @click.self="close()"
             role="dialog" aria-modal="true" aria-label="{{ __('feature.photo_viewer') }}"
             class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-950/90 p-4 backdrop-blur-sm" x-transition.opacity>

            <span class="absolute left-5 top-5 text-sm font-semibold text-white/70"
                  x-text="(i + 1) + ' / ' + photos.length"></span>

            <button type="button" @click="close()" aria-label="{{ __('public.close') }}"
                    class="absolute right-4 top-4 grid h-11 w-11 place-items-center rounded-full bg-white/10 text-white transition hover:bg-white/20">
                <x-ico name="x-mark" class="h-6 w-6" />
            </button>

            <template x-if="photos.length > 1">
                <button type="button" @click="prev()" aria-label="{{ __('public.previous_photo') }}"
                        class="absolute left-3 top-1/2 grid h-11 w-11 -translate-y-1/2 place-items-center rounded-full bg-white/10 text-white transition hover:bg-white/20 sm:left-6">
                    <x-ico name="chevron-left" class="h-6 w-6" />
                </button>
            </template>
            <template x-if="photos.length > 1">
                <button type="button" @click="next()" aria-label="{{ __('public.next_photo') }}"
                        class="absolute right-3 top-1/2 grid h-11 w-11 -translate-y-1/2 place-items-center rounded-full bg-white/10 text-white transition hover:bg-white/20 sm:right-6">
                    <x-ico name="chevron-right" class="h-6 w-6" />
                </button>
            </template>

            <template x-if="i !== null">
                <figure class="mx-10 flex max-h-full w-full max-w-6xl flex-col overflow-hidden rounded-xl bg-slate-900/80 ring-1 ring-white/10 shadow-2xl lg:flex-row" @click.stop>
                    <img :src="photos[i].src" :alt="photos[i].caption || @js($gallery->albumLocalized('title'))"
                         class="max-h-[60vh] w-full object-contain lg:max-h-[80vh] lg:w-2/3">
                    <figcaption class="flex flex-col justify-center gap-3 p-6 text-white lg:w-1/3 lg:p-8">
                        <p class="text-lg font-bold leading-snug" x-text="photos[i].caption || @js($gallery->albumLocalized('title'))"></p>
                        <p class="text-sm text-white/60">{{ $gallery->albumLocalized('title') }}</p>
                        @if ($gallery->published_at)
                            <p class="inline-flex items-center gap-1.5 text-sm text-white/60">
                                <x-ico name="calendar-days" class="h-4 w-4" />
                                {{ $gallery->published_at->translatedFormat('j F Y') }}
                            </p>
                        @endif
                    </figcaption>
                </figure>
            </template>
        </div>
    @else
        <section @class(['container-site py-12', 'photo-archive' => $gallery->is_archive])>
            <x-empty-state icon="photo" title="{{ __('public.no_photos') }}" />
        </section>
    @endif

    </div>

    {{-- Інші альбоми — щоб не заходити в глухий кут наприкінці перегляду --}}
    @if ($others->isNotEmpty())
        <section class="border-t border-slate-200/70 bg-slate-50/80">
            <div class="container-site py-12">
                <h2 class="text-xl font-bold text-slate-900 sm:text-2xl">{{ __('feature.other_albums') }}</h2>
                <div class="accent-rule"></div>

                <div class="mt-7 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($others as $other)
                        <a href="{{ \App\Support\LocalizedUrl::route('galleries.show', $other) }}"
                           @class([
                               'card card-interactive group flex flex-col overflow-hidden',
                               'ring-gold-200/80' => $other->is_archive,
                           ])>
                            <div class="relative aspect-[16/10] overflow-hidden bg-brand-50">
                                @if ($other->cover_url)
                                    <img src="{{ $other->cover_url }}" alt="" loading="lazy" decoding="async"
                                         @class([
                                             'h-full w-full object-cover transition duration-500 group-hover:scale-105',
                                             'sepia-[0.18] contrast-[1.03]' => $other->is_archive,
                                         ])>
                                @else
                                    <div class="flex h-full w-full items-center justify-center bg-gradient-to-br from-brand-700 to-brand-900 text-white/25">
                                        <x-ico name="photo" class="h-12 w-12" />
                                    </div>
                                @endif
                            </div>
                            <div class="flex flex-1 flex-col p-5">
                                <h3 class="line-clamp-2 text-base font-bold leading-snug text-slate-900 transition group-hover:text-brand-700">{{ $other->albumLocalized('title') }}</h3>
                                <div class="mt-auto flex items-center justify-between gap-3 pt-4 text-xs text-slate-400">
                                    @if ($other->published_at)
                                        <span class="inline-flex items-center gap-1.5">
                                            <x-ico name="calendar-days" class="h-3.5 w-3.5" />
                                            {{ $other->published_at->translatedFormat('j F Y') }}
                                        </span>
                                    @else
                                        <span></span>
                                    @endif
                                    <span class="inline-flex shrink-0 items-center gap-1.5">
                                        <x-ico name="photo" class="h-3.5 w-3.5" /> {{ __('feature.photos_count', ['count' => $other->photos_count]) }}
                                    </span>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>

                <div class="mt-10 text-center">
                    <a href="{{ \App\Support\LocalizedUrl::route('galleries.index') }}" class="btn-outline">
                        <x-ico name="arrow-left" class="h-4 w-4" /> {{ __('feature.back_to_gallery') }}
                    </a>
                </div>
            </div>
        </section>
    @else
        <section class="container-site pb-12">
            <a href="{{ \App\Support\LocalizedUrl::route('galleries.index') }}" class="btn-outline">
                <x-ico name="arrow-left" class="h-4 w-4" /> {{ __('feature.back_to_gallery') }}
            </a>
        </section>
    @endif

</x-layouts.app>
