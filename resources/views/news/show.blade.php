<x-layouts.app :title="$news->localized('title')" :description="$news->localized('excerpt')"
               :og-image="$news->cover_image ? asset('storage/' . $news->cover_image) : null">

    {{-- Розмітка NewsArticle для пошукових систем --}}
    @php
        $articleLd = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'NewsArticle',
            'headline' => \Illuminate\Support\Str::limit($news->localized('title'), 110),
            'datePublished' => $news->published_at?->copy()->shiftTimezone('Europe/Kyiv')->toIso8601String(),
            'dateModified' => $news->updated_at?->copy()->shiftTimezone('Europe/Kyiv')->toIso8601String(),
            'image' => $news->cover_image ? [asset('storage/' . $news->cover_image)] : null,
            'mainEntityOfPage' => \App\Support\LocalizedUrl::route('news.show', $news),
            'author' => ['@type' => 'Organization', 'name' => config('app.name')],
            'publisher' => ['@type' => 'Organization', 'name' => config('app.name'), 'url' => \App\Support\LocalizedUrl::to('/')],
        ]);
    @endphp
    <script type="application/ld+json">{!! json_encode($articleLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>

    @php $heritage = $news->usesHeritagePresentation(); @endphp
    <section @class(['bg-brand-950', 'heritage-hero' => $heritage])>
        <div class="container-site py-12 lg:py-14">
            <x-breadcrumbs :items="[
                ['label' => __('public.home'), 'url' => \App\Support\LocalizedUrl::route('home')],
                ['label' => __('public.news'), 'url' => \App\Support\LocalizedUrl::route('news.index')],
                ['label' => $news->localized('title')],
            ]" />
            <h1 class="mt-3 max-w-4xl text-3xl font-extrabold leading-tight text-white sm:text-4xl">{{ $news->localized('title') }}</h1>
            <div class="mt-4 flex flex-wrap items-center gap-3 text-sm text-brand-200">
                @if ($heritage)
                    <span class="badge bg-gold-500/20 text-gold-200 ring-1 ring-gold-400/30">{{ __('public.special_publication') }}</span>
                @endif
                @if ($news->category)
                    <span class="badge bg-white/10 text-brand-100">{{ $news->category->localized('title') }}</span>
                @endif
                @if ($news->published_at)
                    <span class="inline-flex items-center gap-1.5"><x-ico name="calendar-days" class="h-4 w-4" /> {{ $news->published_at->translatedFormat('j F Y') }}</span>
                @endif
                <span class="inline-flex items-center gap-1.5" title="{{ __('public.views') }}"><x-ico name="eye" class="h-4 w-4" /> {{ $news->views }}</span>

                {{-- Вподобайка (без реєстрації) --}}
                <button type="button"
                        x-data="{ likes: {{ (int) $news->likes }}, liked: {{ $liked ? 'true' : 'false' }}, busy: false }"
                        @click="if (busy) return; busy = true;
                                fetch('{{ \App\Support\LocalizedUrl::route('news.like', $news) }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' } })
                                  .then(r => r.json()).then(d => { likes = d.likes; liked = d.liked; })
                                  .finally(() => busy = false)"
                        :class="liked ? 'bg-red-500/25 text-red-100 ring-red-400/50' : 'bg-white/10 text-brand-100 ring-white/15 hover:bg-white/15'"
                        class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 ring-1 transition active:scale-95"
                        :title="liked ? @js(__('public.unlike')) : @js(__('public.like'))">
                    <span x-show="!liked"><x-ico name="heart" class="h-4 w-4" /></span>
                    <span x-show="liked" x-cloak><x-ico name="heart" variant="solid" class="h-4 w-4 text-red-300" /></span>
                    <span x-text="likes">{{ (int) $news->likes }}</span>
                </button>
            </div>
        </div>
    </section>

    <section class="container-site grid gap-10 py-12 lg:grid-cols-12">
        <article class="lg:col-span-8"
                 x-data="{
                     imgs: [], idx: null,
                     init() {
                         this.imgs = [...this.$el.querySelectorAll('img.lightboxable, .prose-site img')];
                         this.imgs.forEach((im, i) => {
                             im.classList.add('cursor-zoom-in');
                             im.addEventListener('click', () => this.idx = i);
                         });
                         window.addEventListener('keydown', e => {
                             if (this.idx === null) return;
                             if (e.key === 'Escape') this.idx = null;
                             if (e.key === 'ArrowRight') this.next();
                             if (e.key === 'ArrowLeft') this.prev();
                         });
                     },
                     next() { this.idx = (this.idx + 1) % this.imgs.length },
                     prev() { this.idx = (this.idx - 1 + this.imgs.length) % this.imgs.length },
                 }"
                 x-effect="document.body.style.overflow = idx === null ? '' : 'hidden'">
            @if ($news->cover_image)
                <x-picture :path="$news->cover_image" :alt="$news->localized('title')" loading="lazy" decoding="async" class="lightboxable mb-8 w-full rounded-2xl object-cover" />
            @endif
            <x-lead-excerpt :excerpt="$news->localized('excerpt')" :body="$news->localized('body')" :heritage="$heritage" />
            <x-prose.article :heritage="$heritage" :date="$news->published_at" :drop-cap="false">
                {!! \App\Support\LocalizedHtml::links($news->localized('body')) !!}
            </x-prose.article>

            {{-- Поділитися новиною --}}
            @php $shareUrl = \App\Support\LocalizedUrl::route('news.show', $news); @endphp
            <div class="mt-10 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-6">
                <span class="mr-1 inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500">
                    <x-ico name="share" class="h-4 w-4" /> {{ __('public.share') }}
                </span>

                {{-- Системне меню (телефони/планшети) --}}
                <button type="button" x-data x-show="typeof navigator.share === 'function'" x-cloak
                        @click="navigator.share({ title: @js($news->localized('title')), url: @js($shareUrl) }).catch(() => {})"
                        class="inline-flex items-center gap-1.5 rounded-full bg-brand-700 px-3.5 py-1.5 text-sm font-medium text-white transition hover:bg-brand-800">
                    {{ __('public.share_button') }}
                </button>

                <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($shareUrl) }}" target="_blank" rel="noopener"
                   title="Facebook" aria-label="{{ __('public.share_facebook') }}"
                   class="grid h-9 w-9 place-items-center rounded-full bg-slate-100 text-slate-600 ring-1 ring-slate-200 transition hover:bg-[#1877f2] hover:text-white hover:ring-[#1877f2]">
                    <svg viewBox="0 0 24 24" fill="currentColor" class="h-4.5 w-4.5"><path d="M13.5 21v-7h2.4l.5-3h-2.9V9.1c0-.9.3-1.6 1.6-1.6h1.4V4.8c-.7-.1-1.5-.2-2.3-.2-2.4 0-4 1.4-4 4V11H7.5v3h2.7v7h3.3Z"/></svg>
                </a>

                <a href="https://t.me/share/url?url={{ urlencode($shareUrl) }}&text={{ urlencode($news->localized('title')) }}" target="_blank" rel="noopener"
                   title="Telegram" aria-label="{{ __('public.share_telegram') }}"
                   class="grid h-9 w-9 place-items-center rounded-full bg-slate-100 text-slate-600 ring-1 ring-slate-200 transition hover:bg-[#229ED9] hover:text-white hover:ring-[#229ED9]">
                    <svg viewBox="0 0 24 24" fill="currentColor" class="h-4.5 w-4.5"><path d="M20.7 4.2 3.3 11c-.8.3-.8 1.4 0 1.7l4.3 1.4 1.6 5c.2.7 1.1.9 1.6.4l2.3-2.2 4.5 3.3c.6.4 1.4.1 1.6-.6l3-14.3c.2-.9-.7-1.6-1.5-1.3ZM9.4 13.9l8.7-5.5c.2-.1.4.2.2.3l-7.2 6.7-.3 3-1.4-4.5Z"/></svg>
                </a>

                <a href="viber://forward?text={{ urlencode($news->localized('title') . ' — ' . $shareUrl) }}"
                   title="Viber" aria-label="{{ __('public.share_viber') }}"
                   class="grid h-9 w-9 place-items-center rounded-full bg-slate-100 text-slate-600 ring-1 ring-slate-200 transition hover:bg-[#7360f2] hover:text-white hover:ring-[#7360f2]">
                    <svg viewBox="0 0 24 24" fill="currentColor" class="h-4.5 w-4.5"><path d="M12 2C8.6 2 4.6 2.8 3.2 6.1c-.7 1.7-.7 3.7-.7 5.6 0 1.9 0 3.9.7 5.6.6 1.4 1.7 2.4 3.1 2.9v2.9c0 .5.6.8 1 .4l2.4-2.3c.7.1 1.5.1 2.3.1 3.4 0 7.4-.8 8.8-4.1.7-1.7.7-3.7.7-5.6 0-1.9 0-3.9-.7-5.6C19.4 2.8 15.4 2 12 2Zm4.8 13.3-.9.9c-.2.2-.5.3-.8.2-1.5-.4-3-1.3-4.2-2.5-1.2-1.2-2.1-2.7-2.5-4.2-.1-.3 0-.6.2-.8l.9-.9c.3-.3.8-.3 1 0l1.2 1.2c.3.3.3.7 0 1l-.4.5c.3.8.8 1.5 1.4 2.1.6.6 1.3 1.1 2.1 1.4l.5-.4c.3-.3.7-.3 1 0l1.2 1.2c.3.3.3.8.3 1.3Z"/></svg>
                </a>

                {{-- Копіювати посилання --}}
                <button type="button"
                        x-data="{
                            copied: false,
                            done() { this.copied = true; setTimeout(() => this.copied = false, 2000) },
                            copy() {
                                const url = @js($shareUrl);
                                if (navigator.clipboard && navigator.clipboard.writeText) {
                                    navigator.clipboard.writeText(url).then(() => this.done()).catch(() => this.legacy(url));
                                } else { this.legacy(url) }
                            },
                            legacy(url) {
                                // Запасний шлях для старих браузерів і середовищ без дозволу clipboard
                                const ta = document.createElement('textarea');
                                ta.value = url; ta.style.position = 'fixed'; ta.style.opacity = '0';
                                document.body.appendChild(ta); ta.select();
                                try { document.execCommand('copy'); this.done() } catch (e) {}
                                ta.remove();
                            }
                        }"
                        @click="copy()"
                        :class="copied ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-slate-100 text-slate-600 ring-slate-200 hover:bg-slate-200'"
                        class="inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-sm font-medium ring-1 transition">
                    <span x-show="!copied" class="inline-flex items-center gap-1.5"><x-ico name="link" class="h-4 w-4" /> {{ __('public.copy_link') }}</span>
                    <span x-show="copied" x-cloak class="inline-flex items-center gap-1.5"><x-ico name="check" class="h-4 w-4" /> {{ __('public.copied') }}</span>
                </button>
            </div>

            <a href="{{ \App\Support\LocalizedUrl::route('news.index') }}" class="btn-outline mt-8">
                <x-ico name="arrow-left" class="h-4 w-4" /> {{ __('public.back_news') }}
            </a>

            {{-- Лайтбокс для фото статті --}}
            <div x-show="idx !== null" x-cloak @click.self="idx = null"
                 class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-950/90 p-4 backdrop-blur-sm">
                <button type="button" @click="idx = null" aria-label="{{ __('public.close') }}"
                        class="absolute right-4 top-4 grid h-11 w-11 place-items-center rounded-full bg-white/10 text-white transition hover:bg-white/20">
                    <x-ico name="x-mark" class="h-6 w-6" />
                </button>

                <template x-if="imgs.length > 1">
                    <div>
                        <button type="button" @click="prev()" aria-label="{{ __('public.previous_photo') }}"
                                class="absolute left-3 top-1/2 grid h-11 w-11 -translate-y-1/2 place-items-center rounded-full bg-white/10 text-white transition hover:bg-white/20">
                            <x-ico name="chevron-left" class="h-6 w-6" />
                        </button>
                        <button type="button" @click="next()" aria-label="{{ __('public.next_photo') }}"
                                class="absolute right-3 top-1/2 grid h-11 w-11 -translate-y-1/2 place-items-center rounded-full bg-white/10 text-white transition hover:bg-white/20">
                            <x-ico name="chevron-right" class="h-6 w-6" />
                        </button>
                        <span class="absolute bottom-4 left-1/2 -translate-x-1/2 rounded-full bg-white/10 px-3 py-1 text-sm text-white"
                              x-text="(idx + 1) + ' / ' + imgs.length"></span>
                    </div>
                </template>

                <img :src="idx !== null ? imgs[idx].src : ''" alt=""
                     class="max-h-[85vh] max-w-full rounded-lg shadow-2xl" @click.stop>
            </div>
        </article>

        <aside class="lg:col-span-4">
            @if ($related->isNotEmpty())
                <div class="card p-6 lg:sticky lg:top-28">
                    <h2 class="text-lg font-bold text-slate-900">{{ __('public.related_news') }}</h2>
                    <div class="accent-rule"></div>
                    <ul class="mt-5 space-y-4">
                        @foreach ($related as $r)
                            <li>
                                <a href="{{ \App\Support\LocalizedUrl::route('news.show', $r) }}" class="group block">
                                    <p class="line-clamp-2 text-sm font-semibold text-slate-800 transition group-hover:text-brand-700">{{ $r->localized('title') }}</p>
                                    @if ($r->published_at)
                                        <p class="mt-1 text-xs text-slate-400">{{ $r->published_at->translatedFormat('j F Y') }}</p>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </aside>
    </section>

</x-layouts.app>
