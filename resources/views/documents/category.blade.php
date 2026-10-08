<x-layouts.app :title="$category->localized('title')"
               :description="__('feature.category_description', ['title' => $category->localized('title')])">

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

        $documentWord = fn (int $n) => $plural($n, __('feature.document'), __('feature.documents'), __('feature.documents_2'));
        // Родовий відмінок для конструкції «із N документів»
        $documentGenitive = fn (int $n) => $plural($n, __('feature.documents_3'), __('feature.documents_2'), __('feature.documents_2'));

        $hasContacts = ! empty($s['contact_phone']) || ! empty($s['contact_email'])
            || ! empty($s['contact_address']) || ! empty($s['work_hours']);
    @endphp

    {{-- Світла шапка розділу — у стилі решти внутрішніх сторінок --}}
    <section class="border-b border-slate-200/70 bg-slate-50/80">
        <div class="container-site py-8 lg:py-10">
            <x-breadcrumbs tone="light" :items="[
                ['label' => __('public.home'), 'url' => \App\Support\LocalizedUrl::route('home')],
                ['label' => __('public.public_information'), 'url' => \App\Support\LocalizedUrl::route('documents.index')],
                ['label' => $category->localized('title')],
            ]" />

            <div class="relative mt-4 overflow-hidden rounded-2xl bg-white px-6 py-8 shadow-sm ring-1 ring-slate-200/80 sm:px-10 sm:py-10">
                {{-- Декоративний контур документа праворуч --}}
                <x-ico name="document-text" aria-hidden="true"
                       class="pointer-events-none absolute -right-6 top-1/2 hidden h-64 w-64 -translate-y-1/2 text-brand-50 lg:block" />

                <div class="relative lg:flex lg:items-start lg:justify-between lg:gap-10">
                    <div class="max-w-3xl">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-brand-700 ring-1 ring-brand-100">
                            <x-ico name="folder" class="h-4 w-4" aria-hidden="true" /> {{ __('public.public_information') }}
                        </span>
                        <h1 class="mt-3 text-3xl font-extrabold leading-tight text-brand-950 sm:text-4xl">{{ $category->localized('title') }}</h1>
                        <div class="accent-rule"></div>
                    </div>

                    {{-- Пошук по назвах документів усередині категорії --}}
                    @if ($totalCount)
                        <form method="get" action="{{ \App\Support\LocalizedUrl::route('documents.category', $category) }}"
                              class="relative mt-6 w-full shrink-0 lg:mt-2 lg:max-w-sm">
                            <label for="document-search" class="sr-only">{{ __('feature.search_documents_in_this_category') }}</label>
                            <x-ico name="magnifying-glass" class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                            <input id="document-search" type="search" name="q" maxlength="{{ \App\Support\SearchQuery::MAX_LENGTH }}" value="{{ $search }}"
                                   placeholder="{{ __('feature.search_documents') }}"
                                   class="w-full rounded-full border-0 bg-white py-3 pl-12 pr-28 text-base text-slate-800 shadow-sm ring-1 ring-slate-200 placeholder:text-slate-400 focus:ring-2 focus:ring-brand-600">
                            <button type="submit" class="absolute right-1.5 top-1/2 inline-flex min-h-11 -translate-y-1/2 items-center rounded-full bg-brand-900 px-4 text-sm font-semibold text-white transition hover:bg-brand-800">
                                {{ __('public.find') }}
                            </button>
                        </form>
                    @endif
                </div>

                @if ($totalCount)
                    <div class="relative mt-6 flex flex-wrap items-center gap-2 text-sm">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-gold-50 px-3 py-1 font-semibold text-gold-700 ring-1 ring-gold-300/70">
                            <x-ico name="document-text" class="h-4 w-4" aria-hidden="true" />
                            {{ $totalCount }} {{ $documentWord($totalCount) }}
                        </span>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-50 px-3 py-1 font-semibold text-brand-700 ring-1 ring-brand-100">
                            <x-ico name="arrow-down-tray" class="h-4 w-4" aria-hidden="true" />
                            {{ __('feature.pdf_format') }}
                        </span>
                        @if ($documents->hasPages())
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-50 px-3 py-1 font-semibold text-brand-800 ring-1 ring-brand-100">
                                <x-ico name="bars-3-bottom-left" class="h-4 w-4" aria-hidden="true" />
                                {{ __('feature.page_of', ['page' => $documents->currentPage(), 'total' => $documents->lastPage()]) }}
                            </span>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </section>

    <section class="container-site space-y-10 py-10 lg:py-12">
        <div class="grid gap-8 lg:grid-cols-12">
            {{-- Навігація по всіх розділах: на мобільному — під списком документів,
                 щоб два десятки посилань не відсували самі документи на екран нижче --}}
            <aside class="order-2 lg:order-1 lg:col-span-3">
                <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/80 lg:sticky lg:top-28 lg:max-h-[calc(100vh-8rem)] lg:overflow-y-auto">
                    <p class="px-2 pb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('feature.all_sections') }}</p>
                    <nav class="space-y-1">
                        @foreach ($categories as $c)
                            <a href="{{ \App\Support\LocalizedUrl::route('documents.category', $c) }}"
                               @if ($c->id === $category->id) aria-current="page" @endif
                               @class([
                                   'flex min-h-11 items-center justify-between gap-3 rounded-lg px-3 py-2 text-sm transition',
                                   'bg-brand-50 font-semibold text-brand-800 ring-1 ring-brand-100' => $c->id === $category->id,
                                   'text-slate-600 hover:bg-slate-50' => $c->id !== $category->id,
                               ])>
                                <span>{{ $c->localized('title') }}</span>
                                <span @class([
                                    'shrink-0 rounded-full px-2 py-0.5 text-xs font-semibold',
                                    'bg-brand-100 text-brand-700' => $c->id === $category->id,
                                    'bg-slate-100 text-slate-500' => $c->id !== $category->id,
                                ])>{{ $c->documents_count }}</span>
                            </a>
                        @endforeach
                    </nav>
                </div>
            </aside>

            <div class="order-1 lg:order-2 lg:col-span-9">
                @if ($sectionPage && $search === '')
                    {{-- Розділ має CMS-сторінку з повним вмістом оригіналу: показуємо її замість списку документів --}}
                    <x-prose.article>
                        {!! \App\Support\FileCards::render(\App\Support\ResponsiveTables::render(\App\Support\LocalizedHtml::links($sectionPage->publicBody()))) !!}
                    </x-prose.article>
                @elseif ($documents->total())
                    <div class="flex flex-wrap items-center justify-between gap-3 pb-4 text-sm text-slate-500">
                        <p>
                            {{ __('feature.showing_range', ['first' => $documents->firstItem(), 'last' => $documents->lastItem(), 'total' => $documents->total()]) }} {{ $documentGenitive($documents->total()) }}
                            @if ($search !== '')
                                {{ __('feature.search_query', ['query' => $search]) }}
                            @endif
                        </p>
                        @if ($search !== '')
                            <a href="{{ \App\Support\LocalizedUrl::route('documents.category', $category) }}" class="inline-flex items-center gap-1.5 font-semibold text-brand-700 hover:text-gold-600">
                                <x-ico name="x-mark" class="h-4 w-4" aria-hidden="true" /> {{ __('feature.reset_search') }}
                            </a>
                        @endif
                    </div>

                    <ul class="space-y-3">
                        @foreach ($documents as $doc)
                            <li>
                                <x-file-card :href="$doc->file_url" :title="$doc->localized('title')"
                                    :extension="$doc->file_extension ?: ''"
                                    :meta="collect([$doc->file_size_label, $doc->published_at?->translatedFormat('j F Y')])->filter()->implode(' · ')"
                                    :description="$doc->localized('description')" :download="(bool) $doc->file_path" />
                            </li>
                        @endforeach
                    </ul>

                    @if ($documents->hasPages())
                        <div class="pt-8">{{ $documents->links() }}</div>
                    @endif
                @elseif ($search !== '')
                    <div class="rounded-2xl bg-white p-10 text-center ring-1 ring-slate-200/80">
                        <x-ico name="magnifying-glass" class="mx-auto h-10 w-10 text-slate-300" aria-hidden="true" />
                        <p class="mt-3 font-semibold text-brand-950">{{ __('feature.documents_none_query', ['query' => $search]) }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ __('feature.try_a_shorter_query_or_browse_the') }}</p>
                        <a href="{{ \App\Support\LocalizedUrl::route('documents.category', $category) }}" class="btn-outline mt-4">{{ __('feature.show_all_documents') }}</a>
                    </div>
                @else
                    <x-empty-state icon="folder-open" title="{{ __('public.no_documents') }}" />
                @endif
            </div>
        </div>

        {{-- Фінальний блок: куди звертатися, якщо потрібного документа немає --}}
        <div class="overflow-hidden rounded-2xl bg-gradient-to-br from-brand-50 to-white px-6 py-8 ring-1 ring-brand-100 sm:px-10">
            <div class="grid gap-8 lg:grid-cols-2 lg:items-center">
                <div>
                    <h2 class="text-2xl font-extrabold text-brand-950">{{ __('feature.cannot_find_the_document_you_need') }}</h2>
                    <p class="mt-2 text-slate-600">
                        {{ __('feature.contact_us_and_we_will_help_you') }}
                    </p>
                    <div class="mt-5 flex flex-wrap gap-3">
                        <a href="{{ \App\Support\LocalizedUrl::route('contacts') }}" class="btn-primary">
                            {{ __('feature.email_us') }} <x-ico name="arrow-right" class="h-4 w-4" aria-hidden="true" />
                        </a>
                        <a href="{{ \App\Support\LocalizedUrl::route('documents.index') }}" class="btn-outline border-gold-300 text-gold-700 ring-gold-300 hover:bg-gold-50">
                            {{ __('feature.all_sections') }} <x-ico name="arrow-right" class="h-4 w-4" aria-hidden="true" />
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
