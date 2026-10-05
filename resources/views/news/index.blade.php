<x-layouts.app :title="__('public.news')">

    <x-page-hero :title="__('public.news_title')" :breadcrumbs="[
        ['label' => __('public.home'), 'url' => \App\Support\LocalizedUrl::route('home')],
        ['label' => __('public.news')],
    ]" />

    <section class="container-site py-12">
        {{-- Фільтр за категоріями та роком --}}
        @if ($categories->isNotEmpty())
            <div class="mb-8 flex flex-wrap items-center gap-2">
                <a href="{{ \App\Support\LocalizedUrl::route('news.index') }}"
                   @class(['rounded-full px-4 py-1.5 text-sm font-medium transition', 'bg-brand-700 text-white' => ! $activeCategory, 'bg-slate-100 text-slate-600 hover:bg-slate-200' => $activeCategory])>
                    {{ __('public.all') }}
                </a>
                @foreach ($categories as $cat)
                    <a href="{{ \App\Support\LocalizedUrl::route('news.index', ['category' => $cat->slug]) }}"
                       @class(['rounded-full px-4 py-1.5 text-sm font-medium transition', 'bg-brand-700 text-white' => $activeCategory?->id === $cat->id, 'bg-slate-100 text-slate-600 hover:bg-slate-200' => $activeCategory?->id !== $cat->id])>
                        {{ $cat->localized('title') }}
                    </a>
                @endforeach

                {{-- Рік (архів) --}}
                @if ($years->count() > 1)
                    <form method="GET" action="{{ \App\Support\LocalizedUrl::route('news.index') }}" class="ml-auto">
                        @if ($activeCategory)
                            <input type="hidden" name="category" value="{{ $activeCategory->slug }}">
                        @endif
                        <select name="year" onchange="this.form.submit()" aria-label="{{ __('public.year') }}"
                                class="rounded-full border-0 bg-slate-100 py-1.5 pl-4 pr-9 text-sm font-medium text-slate-600 ring-0 transition hover:bg-slate-200 focus:ring-2 focus:ring-brand-500">
                            <option value="">{{ __('public.all_years') }}</option>
                            @foreach ($years as $y)
                                <option value="{{ $y }}" @selected($activeYear === $y)>{{ $y }}</option>
                            @endforeach
                        </select>
                    </form>
                @endif
            </div>
        @endif

        @if ($news->isNotEmpty())
            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($news as $item)
                    <x-news-card :item="$item" />
                @endforeach
            </div>
            <div class="mt-10">{{ $news->links() }}</div>
        @else
            <x-empty-state icon="newspaper" :title="__('public.no_news')" />
        @endif
    </section>

</x-layouts.app>
