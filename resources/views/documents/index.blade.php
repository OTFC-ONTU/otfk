<x-layouts.app title="{{ __('public.public_information') }}"
               description="{{ __('feature.public_information_of_odesa_technical_professional_college') }}">

    @php
        $s = \App\Models\Setting::publicMap();

        // Українське відмінювання лічильників
        $plural = function (int $n, string $one, string $few, string $many): string {
            $mod100 = $n % 100;
            $mod10 = $n % 10;

            if ($mod100 >= 11 && $mod100 <= 14) {
                return $many;
            }

            return match (true) {
                $mod10 === 1 => $one,
                $mod10 >= 2 && $mod10 <= 4 => $few,
                default => $many,
            };
        };

        $categoryWord = fn (int $n) => $plural($n, __('feature.category'), __('feature.categories'), __('feature.categories_2'));
        $documentWord = fn (int $n) => $plural($n, __('feature.document'), __('feature.documents'), __('feature.documents_2'));

        $filledCount = $categories->where('documents_count', '>', 0)->count();
        $emptyCount = $categories->count() - $filledCount;
        $largest = $categories->sortByDesc('documents_count')->first();

        $hasContacts = ! empty($s['contact_phone']) || ! empty($s['contact_email'])
            || ! empty($s['contact_address']) || ! empty($s['work_hours']);
    @endphp

    {{-- Світла шапка розділу — у стилі новин, структури, спеціальностей та подій --}}
    <section class="border-b border-slate-200/70 bg-slate-50/80">
        <div class="container-site py-8 lg:py-10">
            <x-breadcrumbs tone="light" :items="[
                ['label' => __('public.home'), 'url' => \App\Support\LocalizedUrl::route('home')],
                ['label' => __('public.public_information')],
            ]" />

            <div class="relative mt-4 overflow-hidden rounded-2xl bg-white px-6 py-8 shadow-sm ring-1 ring-slate-200/80 sm:px-10 sm:py-10">
                {{-- Декоративний контур будівлі — заповнює порожнечу праворуч на великих екранах --}}
                <x-ico name="building-library" aria-hidden="true"
                       class="pointer-events-none absolute -right-8 top-1/2 hidden h-64 w-64 -translate-y-1/2 text-brand-50 lg:block" />

                <div class="relative max-w-3xl">
                    <h1 class="text-3xl font-extrabold leading-tight text-brand-950 sm:text-4xl lg:text-[2.75rem]">{{ __('public.public_information') }}</h1>
                    <div class="accent-rule"></div>
                    <p class="mt-5 text-lg leading-relaxed text-slate-500">
                        {{ __('feature.open_access_to_official_college_documents_and') }}
                    </p>

                    @if ($categories->isNotEmpty())
                        <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <div class="flex items-center gap-3 rounded-xl bg-gold-50 px-4 py-3 ring-1 ring-gold-300/70">
                                <x-ico name="folder" class="h-6 w-6 shrink-0 text-gold-700" aria-hidden="true" />
                                <span class="min-w-0">
                                    <span class="block text-lg font-extrabold leading-tight text-brand-950">{{ $categories->count() }}</span>
                                    <span class="block text-xs text-slate-500">{{ $categoryWord($categories->count()) }}</span>
                                </span>
                            </div>
                            <div class="flex items-center gap-3 rounded-xl bg-brand-50 px-4 py-3 ring-1 ring-brand-100">
                                <x-ico name="document-text" class="h-6 w-6 shrink-0 text-brand-700" aria-hidden="true" />
                                <span class="min-w-0">
                                    <span class="block text-lg font-extrabold leading-tight text-brand-950">{{ $documentsCount }}</span>
                                    <span class="block text-xs text-slate-500">{{ __('feature.documents_public', ['word' => $documentWord($documentsCount)]) }}</span>
                                </span>
                            </div>
                            @if ($largest && $largest->documents_count > 0)
                                <div class="flex items-center gap-3 rounded-xl bg-slate-50 px-4 py-3 ring-1 ring-slate-200">
                                    <x-ico name="star" class="h-6 w-6 shrink-0 text-slate-400" aria-hidden="true" />
                                    <span class="min-w-0">
                                        <span class="block truncate text-sm font-bold leading-tight text-brand-950">{{ $largest->localized('title') }}</span>
                                        <span class="block text-xs text-slate-500">{{ __('feature.largest_section', ['count' => $largest->documents_count, 'word' => $documentWord($largest->documents_count)]) }}</span>
                                    </span>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <section class="container-site space-y-8 py-10 lg:py-12">
        @if ($categories->isNotEmpty())
            {{-- Живий пошук і фільтр по розділах: категорій два десятки, усі вже на сторінці --}}
            <div x-data="{ query: '', filter: 'all', shown: 0 }"
                 x-init="$nextTick(() => shown = $refs.grid.querySelectorAll('[data-category]:not([hidden])').length)"
                 x-effect="query, filter, $nextTick(() => shown = $refs.grid.querySelectorAll('[data-category]:not([hidden])').length)"
                 class="space-y-6">

                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <label class="relative block w-full lg:max-w-md">
                        <span class="sr-only">{{ __('feature.search_document_sections') }}</span>
                        <x-ico name="magnifying-glass" class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                        <input type="search" x-model="query" placeholder="{{ __('feature.search_document_sections_2') }}"
                               class="w-full rounded-full border-0 bg-white py-3 pl-12 pr-4 text-base text-slate-800 shadow-sm ring-1 ring-slate-200 placeholder:text-slate-400 focus:ring-2 focus:ring-brand-600">
                    </label>

                    <div class="flex flex-wrap gap-2">
                        <button type="button" @click="filter = 'all'"
                                :class="filter === 'all' ? 'bg-brand-900 text-white ring-brand-900' : 'bg-white text-slate-600 ring-slate-200 hover:bg-slate-50'"
                                class="inline-flex min-h-11 items-center gap-2 rounded-full px-4 text-sm font-semibold ring-1 transition">
                            {{ __('feature.all_sections') }}
                            <span class="rounded-full bg-black/10 px-2 py-0.5 text-xs">{{ $categories->count() }}</span>
                        </button>
                        <button type="button" @click="filter = 'filled'"
                                :class="filter === 'filled' ? 'bg-brand-900 text-white ring-brand-900' : 'bg-white text-slate-600 ring-slate-200 hover:bg-slate-50'"
                                class="inline-flex min-h-11 items-center gap-2 rounded-full px-4 text-sm font-semibold ring-1 transition">
                            {{ __('feature.with_documents') }}
                            <span class="rounded-full bg-black/10 px-2 py-0.5 text-xs">{{ $filledCount }}</span>
                        </button>
                        @if ($emptyCount)
                            <button type="button" @click="filter = 'empty'"
                                    :class="filter === 'empty' ? 'bg-brand-900 text-white ring-brand-900' : 'bg-white text-slate-600 ring-slate-200 hover:bg-slate-50'"
                                    class="inline-flex min-h-11 items-center gap-2 rounded-full px-4 text-sm font-semibold ring-1 transition">
                            {{ __('feature.empty') }}
                            <span class="rounded-full bg-black/10 px-2 py-0.5 text-xs">{{ $emptyCount }}</span>
                        </button>
                        @endif
                    </div>
                </div>

                <div x-ref="grid" class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    @foreach ($categories as $cat)
                        @php $isEmpty = ! $cat->documents_count; @endphp
                        <a href="{{ \App\Support\LocalizedUrl::route('documents.category', $cat) }}"
                           data-category
                           data-title="{{ mb_strtolower($cat->localized('title')) }}"
                           data-state="{{ $isEmpty ? 'empty' : 'filled' }}"
                           x-show="(filter === 'all' || filter === $el.dataset.state)
                                   && $el.dataset.title.includes(query.trim().toLowerCase())"
                           class="group flex flex-col rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200/80 transition hover:-translate-y-0.5 hover:shadow-md hover:ring-brand-200">
                            <span class="flex items-start justify-between gap-3">
                                <span @class([
                                    'grid h-12 w-12 shrink-0 place-items-center rounded-xl transition',
                                    'bg-brand-50 text-brand-700 group-hover:bg-brand-900 group-hover:text-white' => ! $isEmpty,
                                    'bg-slate-100 text-slate-400' => $isEmpty,
                                ])>
                                    <x-ico :name="$isEmpty ? 'folder-open' : 'folder'" class="h-6 w-6" aria-hidden="true" />
                                </span>
                                @if (! $isEmpty)
                                    <span class="rounded-md bg-rose-50 px-2 py-1 text-[0.65rem] font-bold uppercase tracking-wide text-rose-600 ring-1 ring-rose-100">PDF</span>
                                @endif
                            </span>

                            <span class="mt-4 block font-bold leading-snug text-brand-950 group-hover:text-brand-700">{{ $cat->localized('title') }}</span>

                            <span class="mt-auto flex items-center justify-between gap-3 pt-5 text-sm">
                                <span @class(['font-semibold text-slate-500' => ! $isEmpty, 'text-slate-400' => $isEmpty])>
                                    {{ $isEmpty ? __('feature.coming_soon') : $cat->documents_count . ' ' . $documentWord($cat->documents_count) }}
                                </span>
                                <span class="inline-flex items-center gap-1 font-semibold text-brand-700 group-hover:text-gold-600">
                                    {{ __('feature.open') }} <x-ico name="arrow-right" class="h-4 w-4 transition group-hover:translate-x-1" aria-hidden="true" />
                                </span>
                            </span>
                        </a>
                    @endforeach
                </div>

                {{-- Порожній результат живого пошуку --}}
                <div x-show="shown === 0" x-cloak class="rounded-2xl bg-white p-10 text-center ring-1 ring-slate-200/80">
                    <x-ico name="folder-open" class="mx-auto h-10 w-10 text-slate-300" aria-hidden="true" />
                    <p class="mt-3 font-semibold text-brand-950">{{ __('feature.no_sections_match_your_search') }}</p>
                    <button type="button" @click="query = ''; filter = 'all'" class="btn-outline mt-4">{{ __('feature.reset_search') }}</button>
                </div>
            </div>
        @else
            <x-empty-state icon="folder" title="{{ __('public.no_document_categories') }}" />
        @endif

        {{-- Фінальний блок: куди звертатися, якщо потрібного документа немає --}}
        <div class="overflow-hidden rounded-2xl bg-gradient-to-br from-brand-50 to-white px-6 py-8 ring-1 ring-brand-100 sm:px-10">
            <div class="grid gap-8 lg:grid-cols-2 lg:items-center">
                <div>
                    <h2 class="text-2xl font-extrabold text-brand-950">{{ __('feature.cannot_find_a_document') }}</h2>
                    <p class="mt-2 text-slate-600">
                        {{ __('feature.we_are_ready_to_help_contact_us') }}
                    </p>
                    <div class="mt-5 flex flex-wrap gap-3">
                        <a href="{{ \App\Support\LocalizedUrl::route('contacts') }}" class="btn-primary">
                            {{ __('feature.email_us') }} <x-ico name="arrow-right" class="h-4 w-4" aria-hidden="true" />
                        </a>
                        <a href="{{ \App\Support\LocalizedUrl::route('faq') }}" class="btn-outline border-gold-300 text-gold-700 ring-gold-300 hover:bg-gold-50">
                            {{ __('feature.questions_and_answers') }} <x-ico name="arrow-right" class="h-4 w-4" aria-hidden="true" />
                        </a>
                    </div>
                </div>

                @if ($hasContacts)
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
                        @if (! empty($s['contact_address']))
                            <div class="flex items-start gap-3">
                                <x-ico name="map-pin" class="mt-0.5 h-5 w-5 shrink-0 text-gold-600" aria-hidden="true" />
                                <span class="min-w-0">
                                    <dt class="text-xs uppercase tracking-wide text-slate-400">{{ __('public.address') }}</dt>
                                    <dd class="text-slate-600">{{ $s['contact_address'] }}</dd>
                                </span>
                            </div>
                        @endif
                        @if (! empty($s['work_hours']))
                            <div class="flex items-start gap-3">
                                <x-ico name="clock" class="mt-0.5 h-5 w-5 shrink-0 text-gold-600" aria-hidden="true" />
                                <span class="min-w-0">
                                    <dt class="text-xs uppercase tracking-wide text-slate-400">{{ __('public.hours') }}</dt>
                                    <dd class="text-slate-600">{{ $s['work_hours'] }}</dd>
                                </span>
                            </div>
                        @endif
                    </dl>
                @endif
            </div>
        </div>
    </section>

</x-layouts.app>
