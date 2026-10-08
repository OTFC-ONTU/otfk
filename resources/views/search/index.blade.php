<x-layouts.app :title="$q !== '' ? __('feature.search_title', ['query' => $q]) : __('public.site_search')"
               description="{{ __('feature.search_news_pages_specialties_documents_and_events_2') }}"
               robots="noindex, follow">

    @php
        $s = \App\Models\Setting::publicMap();

        // Родовий відмінок лічильника — для конструкції «із N результатів»
        $resultWord = function (int $n): string {
            $mod100 = $n % 100;

            return $n % 10 === 1 && ($mod100 < 11 || $mod100 > 14) ? __('feature.results') : __('feature.results_2');
        };

        // Підсвічування збігу в назві: ділимо вихідний текст за збігом і екрануємо кожен шматок окремо,
        // щоб запит на кшталт «amp» не потрапляв усередину HTML-сутностей; теги <mark> — лише наші
        $highlight = function (?string $text) use ($q): string {
            $text = (string) $text;
            $parts = $q === '' ? false : preg_split('/(' . preg_quote($q, '/') . ')/iu', $text, -1, PREG_SPLIT_DELIM_CAPTURE);

            if ($parts === false) {
                return e($text);
            }

            return collect($parts)->map(fn (string $part, int $i) => $i % 2
                ? '<mark class="rounded bg-gold-100 px-0.5 text-brand-950">' . e($part) . '</mark>'
                : e($part))->implode('');
        };

        $tooShort = $q !== '' && mb_strlen($q) < 2;
    @endphp

    {{-- Світла шапка з полем пошуку — у стилі решти внутрішніх сторінок --}}
    <section class="border-b border-slate-200/70 bg-slate-50/80">
        <div class="container-site py-8 lg:py-10">
            <x-breadcrumbs tone="light" :items="[
                ['label' => __('public.home'), 'url' => \App\Support\LocalizedUrl::route('home')],
                ['label' => __('public.search')],
            ]" />

            <div class="relative mt-4 overflow-hidden rounded-2xl bg-white px-6 py-8 shadow-sm ring-1 ring-slate-200/80 sm:px-10 sm:py-10">
                <x-ico name="magnifying-glass" aria-hidden="true"
                       class="pointer-events-none absolute -right-8 top-1/2 hidden h-64 w-64 -translate-y-1/2 text-brand-50 lg:block" />

                <div class="relative max-w-3xl">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-brand-700 ring-1 ring-brand-100">
                        <x-ico name="magnifying-glass" class="h-4 w-4" aria-hidden="true" /> {{ __('public.site_search') }}
                    </span>

                    <h1 class="mt-3 text-3xl font-extrabold leading-tight text-brand-950 sm:text-4xl">
                        @if ($q !== '')
                            {{ __('feature.search_results_query', ['query' => $q]) }}
                        @else
                            {{ __('feature.search_prompt') }}
                        @endif
                    </h1>
                    <div class="accent-rule"></div>

                    <form method="get" action="{{ \App\Support\LocalizedUrl::route('search') }}" class="relative mt-6 max-w-2xl">
                        <label for="site-search" class="sr-only">{{ __('public.site_search') }}</label>
                        <x-ico name="magnifying-glass" class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                        <input id="site-search" type="search" name="q" maxlength="{{ \App\Support\SearchQuery::MAX_LENGTH }}" value="{{ $q }}" autofocus
                               placeholder="{{ __('feature.news_specialties_documents') }}"
                               class="w-full rounded-full border-0 bg-white py-3.5 pl-12 pr-28 text-base text-slate-800 shadow-sm ring-1 ring-slate-200 placeholder:text-slate-400 focus:ring-2 focus:ring-brand-600 sm:pr-32">
                        <button type="submit" class="absolute right-1.5 top-1/2 inline-flex min-h-11 -translate-y-1/2 items-center rounded-full bg-brand-900 px-5 text-sm font-semibold text-white transition hover:bg-brand-800">
                            {{ __('public.find') }}
                        </button>
                    </form>

                    @if ($total > 0)
                        {{-- Чипи-фільтри за типом результату --}}
                        <div class="mt-6 flex flex-wrap items-center gap-2 text-sm">
                            <a href="{{ \App\Support\LocalizedUrl::route('search', ['q' => $q]) }}"
                               @if ($type === '') aria-current="true" @endif
                               @class([
                                   'inline-flex min-h-11 items-center gap-1.5 rounded-full px-3 py-1 font-semibold transition',
                                   'bg-gold-50 text-gold-700 ring-1 ring-gold-300/70' => $type === '',
                                   'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50' => $type !== '',
                               ])>
                                {{ __('public.all') }}
                                <span class="text-xs opacity-70">{{ $total }}</span>
                            </a>

                            @foreach ($groups as $key => [$label, $plural, $icon])
                                @continue(! ($counts[$key] ?? 0))
                                <a href="{{ \App\Support\LocalizedUrl::route('search', ['q' => $q, 'type' => $key]) }}"
                                   @if ($type === $key) aria-current="true" @endif
                                   @class([
                                       'inline-flex min-h-11 items-center gap-1.5 rounded-full px-3 py-1 font-semibold transition',
                                       'bg-gold-50 text-gold-700 ring-1 ring-gold-300/70' => $type === $key,
                                       'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50' => $type !== $key,
                                   ])>
                                    <x-ico :name="$icon" class="h-4 w-4" aria-hidden="true" />
                                    {{ $plural }}
                                    <span class="text-xs opacity-70">{{ $counts[$key] }}</span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <section class="container-site space-y-10 py-10 lg:py-12">
        @if ($q === '' || $tooShort)
            {{-- Порожній або надто короткий запит: підказуємо, що взагалі можна знайти --}}
            <div class="rounded-2xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-200/80 sm:p-10">
                <x-ico name="magnifying-glass" class="mx-auto h-10 w-10 text-slate-300" aria-hidden="true" />
                <p class="mt-3 text-lg font-bold text-brand-950">
                    {{ $tooShort ? __('feature.enter_at_least_two_characters') : __('feature.enter_a_search_query_above') }}
                </p>
                <p class="mt-1 text-sm text-slate-500">
                    {{ __('feature.search_news_pages_specialties_documents_and_events') }}
                </p>

                <div class="mx-auto mt-6 grid max-w-3xl gap-3 text-left sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($groups as $key => [$label, $plural, $icon])
                        <div class="flex items-center gap-3 rounded-xl bg-slate-50 px-4 py-3 ring-1 ring-slate-200/70">
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-white text-brand-700 ring-1 ring-slate-200">
                                <x-ico :name="$icon" class="h-5 w-5" aria-hidden="true" />
                            </span>
                            <span class="text-sm font-semibold text-brand-950">{{ $plural }}</span>
                        </div>
                    @endforeach
                </div>

                @if ($quickLinks->isNotEmpty())
                    <p class="mt-8 text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('feature.popular_sections') }}</p>
                    <div class="mt-3 flex flex-wrap justify-center gap-2">
                        @foreach ($quickLinks as $link)
                            <a href="{{ $link['url'] }}"
                               class="inline-flex min-h-11 items-center rounded-full bg-white px-3 py-1 text-sm font-semibold text-brand-700 ring-1 ring-slate-200 transition hover:bg-brand-50">
                                {{ $link['label'] }}
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        @elseif ($total === 0)
            {{-- Нічого не знайдено: пояснюємо, що робити далі --}}
            <div class="rounded-2xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-200/80 sm:p-10">
                <x-ico name="face-frown" class="mx-auto h-10 w-10 text-slate-300" aria-hidden="true" />
                <p class="mt-3 text-lg font-bold text-brand-950">{{ __('feature.search_none_query', ['query' => $q]) }}</p>
                <p class="mt-1 text-sm text-slate-500">
                    {{ __('feature.try_a_shorter_query_or_check_your') }}
                </p>

                <div class="mt-6 flex flex-wrap justify-center gap-3">
                    <a href="{{ \App\Support\LocalizedUrl::route('documents.index') }}" class="btn-outline">
                        {{ __('public.public_information') }} <x-ico name="arrow-right" class="h-4 w-4" aria-hidden="true" />
                    </a>
                    <a href="{{ \App\Support\LocalizedUrl::route('news.index') }}" class="btn-outline">
                        {{ __('public.news') }} <x-ico name="arrow-right" class="h-4 w-4" aria-hidden="true" />
                    </a>
                    <a href="{{ \App\Support\LocalizedUrl::route('contacts') }}" class="btn-primary">
                        {{ __('feature.ask_the_college') }} <x-ico name="arrow-right" class="h-4 w-4" aria-hidden="true" />
                    </a>
                </div>

                @if ($quickLinks->isNotEmpty())
                    <p class="mt-8 text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('feature.site_sections') }}</p>
                    <div class="mt-3 flex flex-wrap justify-center gap-2">
                        @foreach ($quickLinks as $link)
                            <a href="{{ $link['url'] }}"
                               class="inline-flex min-h-11 items-center rounded-full bg-white px-3 py-1 text-sm font-semibold text-brand-700 ring-1 ring-slate-200 transition hover:bg-brand-50">
                                {{ $link['label'] }}
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        @else
            <div>
                <div class="flex flex-wrap items-center justify-between gap-3 pb-4 text-sm text-slate-500">
                    <p>
                        {{ __('feature.showing_range', ['first' => $results->firstItem(), 'last' => $results->lastItem(), 'total' => $results->total()]) }}
                        {{ $resultWord($results->total()) }}
                        @if ($type !== '')
                            {{ __('feature.in_section', ['title' => $groups[$type][1]]) }}
                        @endif
                    </p>
                    @if ($type !== '')
                        <a href="{{ \App\Support\LocalizedUrl::route('search', ['q' => $q]) }}" class="inline-flex items-center gap-1.5 font-semibold text-brand-700 hover:text-gold-600">
                            <x-ico name="x-mark" class="h-4 w-4" aria-hidden="true" /> {{ __('feature.show_all_types') }}
                        </a>
                    @endif
                </div>

                <ul class="space-y-3">
                    @foreach ($results as $r)
                        <li class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/80 transition hover:shadow-md hover:ring-brand-200 sm:p-5">
                            <div class="flex items-start gap-4">
                                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-700 ring-1 ring-brand-100">
                                    <x-ico :name="$r['icon']" class="h-6 w-6" aria-hidden="true" />
                                </span>

                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                        <span class="text-xs font-semibold uppercase tracking-wide text-gold-700">{{ $r['label'] }}</span>
                                        @if ($r['meta'])
                                            <span class="text-xs text-slate-400">{{ $r['meta'] }}</span>
                                        @endif
                                    </div>

                                    <a href="{{ $r['url'] }}" aria-label="{{ $r['title'] }}"
                                       @if ($r['external']) target="_blank" rel="noopener" @endif
                                       class="mt-1 block font-semibold leading-snug text-brand-950 hover:text-brand-700">
                                        {!! $highlight($r['title']) !!}
                                        @if ($r['external'])
                                            <x-ico name="arrow-top-right-on-square" class="ml-1 inline h-4 w-4 align-[-2px] text-slate-400" aria-hidden="true" />
                                        @endif
                                    </a>

                                    @if ($r['excerpt'])
                                        <p class="mt-1.5 text-sm text-slate-500">{{ $r['excerpt'] }}</p>
                                    @endif
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>

                @if ($results->hasPages())
                    <div class="pt-8">{{ $results->links() }}</div>
                @endif
            </div>
        @endif

        {{-- Фінальний блок — як на решті внутрішніх сторінок --}}
        <div class="overflow-hidden rounded-2xl bg-gradient-to-br from-brand-50 to-white px-6 py-8 ring-1 ring-brand-100 sm:px-10">
            <div class="grid gap-8 lg:grid-cols-2 lg:items-center">
                <div>
                    <h2 class="text-2xl font-extrabold text-brand-950">{{ __('feature.cannot_find_the_information_you_need') }}</h2>
                    <p class="mt-2 text-slate-600">
                        {{ __('feature.email_or_call_us_for_help_finding') }}
                    </p>
                    <div class="mt-5 flex flex-wrap gap-3">
                        <a href="{{ \App\Support\LocalizedUrl::route('contacts') }}" class="btn-primary">
                            {{ __('feature.email_us') }} <x-ico name="arrow-right" class="h-4 w-4" aria-hidden="true" />
                        </a>
                        <a href="{{ \App\Support\LocalizedUrl::route('faq') }}" class="btn-outline border-gold-300 text-gold-700 ring-gold-300 hover:bg-gold-50">
                            {{ __('feature.frequently_asked_questions') }} <x-ico name="arrow-right" class="h-4 w-4" aria-hidden="true" />
                        </a>
                    </div>
                </div>

                <dl class="grid gap-4 sm:grid-cols-2">
                    @if (! empty($s['contact_phone']))
                        <div class="flex items-start gap-3">
                            <x-ico name="phone" class="mt-0.5 h-5 w-5 shrink-0 text-gold-600" aria-hidden="true" />
                            <span>
                                <dt class="text-xs uppercase tracking-wide text-slate-400">{{ __('public.phone') }}</dt>
                                <dd><a href="tel:{{ preg_replace('/[^+\d]/', '', $s['contact_phone']) }}" class="font-semibold text-brand-800 hover:text-brand-600">{{ $s['contact_phone'] }}</a></dd>
                            </span>
                        </div>
                    @endif
                    @if (! empty($s['contact_email']))
                        <div class="flex items-start gap-3">
                            <x-ico name="envelope" class="mt-0.5 h-5 w-5 shrink-0 text-gold-600" aria-hidden="true" />
                            <span class="min-w-0">
                                <dt class="text-xs uppercase tracking-wide text-slate-400">Email</dt>
                                <dd><a href="mailto:{{ $s['contact_email'] }}" class="break-words font-semibold text-brand-800 hover:text-brand-600">{{ $s['contact_email'] }}</a></dd>
                            </span>
                        </div>
                    @endif
                </dl>
            </div>
        </div>
    </section>

</x-layouts.app>
