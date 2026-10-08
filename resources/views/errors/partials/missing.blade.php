{{-- Спільний макет 404/410: код, тексти й заголовок передає errors/404 чи errors/410 --}}
<x-layouts.app :title="$title" robots="noindex, follow">

    <section class="border-b border-slate-200/70 bg-slate-50/80">
        <div class="container-site py-12 lg:py-16">
            <div class="relative overflow-hidden rounded-2xl bg-white px-6 py-10 shadow-sm ring-1 ring-slate-200/80 sm:px-10 sm:py-12">
                {{-- Декоративний код помилки праворуч — той самий прийом, що на внутрішніх сторінках --}}
                <p aria-hidden="true"
                   class="pointer-events-none absolute -right-4 top-1/2 hidden -translate-y-1/2 font-display text-[12rem] font-extrabold leading-none text-brand-50 lg:block">{{ $code }}</p>

                <div class="relative max-w-2xl">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-gold-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-gold-700 ring-1 ring-gold-300/70">
                        <x-ico name="exclamation-triangle" class="h-4 w-4" aria-hidden="true" /> {{ $badge }}
                    </span>
                    <h1 class="mt-3 text-3xl font-extrabold leading-tight text-brand-950 sm:text-4xl">{{ $title }}</h1>
                    <div class="accent-rule"></div>
                    <p class="mt-5 text-lg leading-relaxed text-slate-500">
                        {{ $text }}
                    </p>

                    {{-- Живий пошук прямо на сторінці помилки --}}
                    <div x-data="liveSearch(@js(\App\Support\LocalizedUrl::route('search.suggest')), @js(\App\Support\LocalizedUrl::route('search')))"
                         @click.outside="open = false" class="relative mt-6 max-w-xl">
                        <form action="{{ \App\Support\LocalizedUrl::route('search') }}" method="GET" class="relative">
                            <label for="error-search" class="sr-only">{{ __('public.site_search') }}</label>
                            <x-ico name="magnifying-glass" class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                            <input id="error-search" type="search" name="q" maxlength="{{ \App\Support\SearchQuery::MAX_LENGTH }}" placeholder="{{ __('public.search_missing') }}" autocomplete="off"
                                   x-model="q" @input.debounce.250ms="suggest()"
                                   class="w-full rounded-full border-0 bg-white py-3.5 pl-12 pr-28 text-base text-slate-800 shadow-sm ring-1 ring-slate-200 placeholder:text-slate-400 focus:ring-2 focus:ring-brand-600">
                            <button type="submit" class="absolute right-1.5 top-1/2 inline-flex min-h-11 -translate-y-1/2 items-center rounded-full bg-brand-900 px-5 text-sm font-semibold text-white transition hover:bg-brand-800">
                                {{ __('public.find') }}
                            </button>
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

                    <div class="mt-7 flex flex-wrap gap-3">
                        <a href="{{ \App\Support\LocalizedUrl::route('home') }}" class="btn-primary">
                            <x-ico name="home" class="h-4 w-4" aria-hidden="true" /> {{ __('public.back_home') }}
                        </a>
                        <a href="{{ \App\Support\LocalizedUrl::route('news.index') }}" class="btn-outline">{{ __('public.news') }}</a>
                        <a href="{{ \App\Support\LocalizedUrl::route('bells') }}" class="btn-outline">{{ __('public.bells') }}</a>
                        <a href="{{ \App\Support\LocalizedUrl::route('contacts') }}" class="btn-outline">{{ __('public.contacts') }}</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Сталі напрямки для абітурієнта зі старого посилання: не залежать від CMS-меню та JS --}}
    <section class="container-site py-10 lg:py-12" aria-labelledby="not-found-next">
        <h2 id="not-found-next" class="text-lg font-bold text-brand-950">{{ $moved }}</h2>
        <div class="mt-4 grid gap-3 sm:grid-cols-3">
            @foreach ([
                ['href' => \App\Support\LocalizedUrl::to('/abituriyentu'), 'icon' => 'academic-cap', 'label' => __('public.not_found_admission'), 'text' => __('public.not_found_admission_text')],
                ['href' => \App\Support\LocalizedUrl::route('specialties.index'), 'icon' => 'book-open', 'label' => __('public.specialties'), 'text' => __('public.not_found_specialties_text')],
                ['href' => \App\Support\LocalizedUrl::route('search'), 'icon' => 'magnifying-glass', 'label' => __('public.site_search'), 'text' => __('public.not_found_search_text')],
            ] as $link)
                <a href="{{ $link['href'] }}" data-not-found-link
                   class="flex min-h-16 items-start gap-3 rounded-2xl bg-white px-5 py-4 shadow-sm ring-1 ring-slate-200/80 transition hover:shadow-md hover:ring-brand-200">
                    <x-ico :name="$link['icon']" class="mt-0.5 h-5 w-5 shrink-0 text-brand-700" aria-hidden="true" />
                    <span class="min-w-0">
                        <span class="block font-semibold text-brand-950">{{ $link['label'] }}</span>
                        <span class="mt-0.5 block text-sm text-slate-500">{{ $link['text'] }}</span>
                    </span>
                </a>
            @endforeach
        </div>
    </section>

    {{-- Куди піти далі: верхній рівень меню з адмінки --}}
    @php $sections = \App\Models\MenuItem::navigation()->take(8); @endphp
    @if ($sections->isNotEmpty())
        <section class="container-site pb-10 lg:pb-12">
            <h2 class="text-lg font-bold text-brand-950">{{ __('feature.popular_sections') }}</h2>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($sections as $item)
                    @continue($item->href === '#')
                    <a href="{{ $item->href }}"
                       class="flex min-h-16 items-center justify-between gap-3 rounded-2xl bg-white px-5 py-4 shadow-sm ring-1 ring-slate-200/80 transition hover:shadow-md hover:ring-brand-200">
                        <span class="font-semibold text-brand-950">{{ $item->label }}</span>
                        <x-ico name="arrow-right" class="h-4 w-4 shrink-0 text-gold-600" aria-hidden="true" />
                    </a>
                @endforeach
            </div>
        </section>
    @endif

</x-layouts.app>
