<x-layouts.app :title="__('public.not_found')">
    <section class="container-site flex min-h-[55vh] flex-col items-center justify-center py-20 text-center">
        <p class="font-display text-7xl font-extrabold leading-none text-brand-700 sm:text-8xl">404</p>
        <h1 class="mt-5 text-2xl font-bold text-slate-900 sm:text-3xl">{{ __('public.not_found') }}</h1>
        <p class="mt-3 max-w-md text-slate-500">{{ __('public.not_found_text') }}</p>

        {{-- Живий пошук прямо на сторінці помилки --}}
        <div x-data="liveSearch(@js(\App\Support\LocalizedUrl::route('search.suggest')), @js(\App\Support\LocalizedUrl::route('search')))"
             @click.outside="open = false" class="relative mt-7 w-full max-w-md">
            <form action="{{ \App\Support\LocalizedUrl::route('search') }}" method="GET" class="relative">
                <x-ico name="magnifying-glass" class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
                <input type="search" name="q" placeholder="{{ __('public.search_missing') }}" autocomplete="off"
                       x-model="q" @input.debounce.250ms="suggest()"
                       class="input w-full rounded-full py-3 pl-11 pr-4 text-base" />
            </form>
            <div x-show="open && items.length" x-cloak
                 class="absolute left-0 right-0 top-full z-50 mt-2 overflow-hidden rounded-xl border border-slate-200 bg-white text-left shadow-2xl">
                <template x-for="it in items" :key="it.url + it.title">
                    <a :href="it.url" class="flex items-center gap-2.5 px-3.5 py-2.5 transition hover:bg-brand-50">
                        <span class="shrink-0 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500" x-text="it.group"></span>
                        <span class="min-w-0 truncate text-sm text-slate-700" x-text="it.title"></span>
                    </a>
                </template>
            </div>
        </div>

        <div class="mt-7 flex flex-wrap justify-center gap-3">
            <a href="{{ \App\Support\LocalizedUrl::route('home') }}" class="btn-accent">{{ __('public.back_home') }}</a>
            <a href="{{ \App\Support\LocalizedUrl::route('news.index') }}" class="btn-outline">{{ __('public.news') }}</a>
            <a href="{{ \App\Support\LocalizedUrl::route('bells') }}" class="btn-outline">{{ __('public.bells') }}</a>
            <a href="{{ \App\Support\LocalizedUrl::route('contacts') }}" class="btn-outline">{{ __('public.contacts') }}</a>
        </div>
    </section>
</x-layouts.app>
