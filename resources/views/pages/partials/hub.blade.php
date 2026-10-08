{{-- Варіант шаблону: розділ-хаб зі списком дочірніх сторінок (Абітурієнту, Студенту, Про коледж) --}}

@php
    // Пошук по назвах — на клієнті, без запитів на сервер (сторінок у розділі до кількох десятків)
    $needles = $rest->map(fn ($child) => mb_strtolower($child->localized('title')))->values();

    // Короткий опис іде вступом над картками; довгий текст (як-от «Виховна робота») —
    // окремою статтею «Про розділ» під картками
    $bodyIsLong = mb_strlen(trim(strip_tags((string) $page->publicBody()))) > 800;

    // Короткий опис — вступом над картками, без рядків-посилань, що дублюють ці картки
    $intro = $bodyIsLong ? '' : \App\Support\HubIntro::withoutChildLinks($page->publicBody(), $children->pluck('slug'));
@endphp

<section class="container-site py-10 lg:py-14">

    @if ($page->cover_image)
        <x-picture :path="$page->cover_image" :alt="$page->localized('title')" sized decoding="async"
                   class="mb-8 max-h-80 w-full rounded-2xl object-cover" />
    @endif

    @if ($intro !== '')
        <x-prose.article :drop-cap="false" class="mb-10 !max-w-none">
            {!! \App\Support\LazyMedia::render(\App\Support\FileCards::render(\App\Support\ResponsiveTables::render(\App\Support\LocalizedHtml::links($intro))), ! $page->cover_image) !!}
        </x-prose.article>
    @endif

    {{-- Ключові дії розділу — сторінки з прапорцем «Ключова сторінка розділу» в адмінці --}}
    @if ($featured->isNotEmpty())
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 class="text-2xl font-extrabold text-brand-950">{{ __('feature.key_actions') }}</h2>
                <div class="accent-rule"></div>
            </div>
        </div>
        <div class="mt-6 grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($featured as $i => $child)
                <a href="{{ \App\Support\LocalizedUrl::to('/' . $child->slug) }}"
                   class="card card-interactive group flex items-start gap-4 p-5">
                    <span @class([
                        'grid h-12 w-12 shrink-0 place-items-center rounded-xl',
                        'bg-gold-500 text-white' => $i % 2 === 0,
                        'bg-brand-950 text-gold-400' => $i % 2 !== 0,
                    ])>
                        <x-ico name="bookmark" class="h-6 w-6" aria-hidden="true" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block font-bold leading-snug text-brand-950 group-hover:text-brand-700">{{ $child->localized('title') }}</span>
                        @if (filled($child->localized('excerpt')))
                            <span class="mt-1 block text-sm leading-relaxed text-slate-500">{{ \Illuminate\Support\Str::limit($child->localized('excerpt'), 90) }}</span>
                        @endif
                    </span>
                    <x-ico name="arrow-right" class="mt-1 h-5 w-5 shrink-0 text-gold-500 transition group-hover:translate-x-1" aria-hidden="true" />
                </a>
            @endforeach
        </div>
    @endif

    <div @class(['grid gap-8 lg:grid-cols-4 lg:items-start', 'mt-12' => $featured->isNotEmpty()])>

        {{-- Усі сторінки розділу: живий пошук + пронумеровані картки --}}
        <div class="lg:col-span-3"
             x-data="{
                 q: '',
                 items: @js($needles),
                 get needle() { return this.q.trim().toLowerCase() },
                 match(i) { return this.needle === '' || this.items[i].includes(this.needle) },
                 get found() {
                     return this.needle === ''
                         ? this.items.length
                         : this.items.filter(t => t.includes(this.needle)).length
                 },
             }">

            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-extrabold text-brand-950">{{ __('feature.all_pages_in_this_section') }}</h2>
                    <div class="accent-rule"></div>
                </div>
                @if ($rest->count() > 5)
                    <div class="relative w-full sm:w-80">
                        <x-ico name="magnifying-glass" aria-hidden="true"
                               class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
                        <label for="hub-search" class="sr-only">{{ __('feature.search_pages_in_this_section') }}</label>
                        <input id="hub-search" type="search" x-model="q" autocomplete="off"
                               placeholder="{{ __('feature.search_pages_in_this_section_2') }}"
                               class="w-full rounded-xl border-0 bg-white py-3 pl-12 pr-4 text-base text-slate-900 shadow-sm ring-1 ring-slate-200/80 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-400">
                    </div>
                @endif
            </div>

            @if ($rest->count() > 5)
                <p class="mt-3 text-sm text-slate-500" x-show="needle !== ''" x-cloak>
                    {{ __('feature.found') }} <span class="font-semibold text-brand-800" x-text="found"></span>
                    <span x-text="found === 1 ? @js(__('feature.page_2')) : (found >= 2 && found <= 4 ? @js(__('feature.pages')) : @js(__('feature.pages_2')))"></span>
                </p>
            @endif

            <div class="mt-6 grid gap-4 sm:grid-cols-2 2xl:grid-cols-3">
                @foreach ($rest as $i => $child)
                    <a href="{{ \App\Support\LocalizedUrl::to('/' . $child->slug) }}" x-show="match({{ $i }})"
                       class="card card-interactive group flex items-center gap-4 p-4">
                        <span class="shrink-0 rounded-lg bg-slate-100 px-2.5 py-1 font-mono text-sm font-bold text-brand-800 transition group-hover:bg-brand-950 group-hover:text-gold-400">
                            {{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}
                        </span>
                        <span class="min-w-0 flex-1 font-semibold leading-snug text-slate-800 group-hover:text-brand-700">{{ $child->localized('title') }}</span>
                        <x-ico name="arrow-right" class="h-5 w-5 shrink-0 text-slate-300 transition group-hover:translate-x-1 group-hover:text-brand-600" aria-hidden="true" />
                    </a>
                @endforeach
            </div>

            @if ($rest->count() > 5)
                <div class="mt-6" x-show="found === 0" x-cloak>
                    <x-empty-state icon="magnifying-glass" title="{{ __('feature.no_pages_match_this_search') }}">
                        <button type="button" @click="q = ''" class="btn-outline">{{ __('feature.reset_search') }}</button>
                    </x-empty-state>
                </div>
            @endif

            @if ($rest->isEmpty() && $featured->isEmpty())
                <x-empty-state icon="document-text" title="{{ __('public.no_page_content') }}" />
            @endif

            @if (filled($page->publicBody()) && $bodyIsLong)
                <div id="pro-rozdil" class="mt-12">
                    <h2 class="text-2xl font-extrabold text-brand-950">{{ __('feature.about_this_section') }}</h2>
                    <div class="accent-rule"></div>
                    <x-prose.article :drop-cap="false" class="mt-6 !max-w-none">
                        {!! \App\Support\LazyMedia::render(\App\Support\FileCards::render(\App\Support\ResponsiveTables::render(\App\Support\LocalizedHtml::links($page->publicBody()))), ! $page->cover_image) !!}
                    </x-prose.article>
                </div>
            @endif
        </div>

        {{-- Сайдбар розділу: прямий контакт.
             Картка замінює на хабі спільну фінальну смугу (show.blade.php), щоб на мобільному не було двох однакових закликів поспіль. --}}
        <aside class="space-y-6 lg:sticky lg:top-24 lg:self-start">
            <div class="card bg-brand-50/60 p-6 ring-brand-100">
                <h2 class="text-lg font-bold text-brand-950">{{ __('feature.cannot_find_the_page_you_need') }}</h2>
                <p class="mt-2 text-sm leading-relaxed text-slate-600">
                    {{ __('feature.contact_the_admissions_office_for_help_and') }}
                </p>

                @if ($hasContacts)
                    <ul class="mt-5 space-y-3 border-t border-brand-100 pt-5 text-sm text-slate-600">
                        @if (! empty($s['contact_phone']))
                            <li class="flex gap-3">
                                <x-ico name="phone" class="mt-0.5 h-4 w-4 shrink-0 text-gold-600" aria-hidden="true" />
                                <a href="tel:{{ preg_replace('/[^+\d]/', '', $s['contact_phone']) }}" class="font-semibold text-brand-800 hover:text-brand-600">{{ $s['contact_phone'] }}</a>
                            </li>
                        @endif
                        @if (! empty($s['contact_email']))
                            <li class="flex gap-3">
                                <x-ico name="envelope" class="mt-0.5 h-4 w-4 shrink-0 text-gold-600" aria-hidden="true" />
                                <a href="mailto:{{ $s['contact_email'] }}" class="break-all font-semibold text-brand-800 hover:text-brand-600">{{ $s['contact_email'] }}</a>
                            </li>
                        @endif
                        @if (! empty($s['contact_address']))
                            <li class="flex gap-3">
                                <x-ico name="map-pin" class="mt-0.5 h-4 w-4 shrink-0 text-gold-600" aria-hidden="true" />
                                <span>{{ $s['contact_address'] }}</span>
                            </li>
                        @endif
                        @if (! empty($s['work_hours']))
                            <li class="flex gap-3">
                                <x-ico name="clock" class="mt-0.5 h-4 w-4 shrink-0 text-gold-600" aria-hidden="true" />
                                <span>{{ $s['work_hours'] }}</span>
                            </li>
                        @endif
                    </ul>
                @endif

                <div class="mt-6 space-y-3">
                    <a href="{{ \App\Support\LocalizedUrl::route('contacts') }}" class="btn-primary w-full">
                        {{ __('feature.college_contacts') }} <x-ico name="arrow-right" class="h-4 w-4" />
                    </a>
                    <a href="{{ \App\Support\LocalizedUrl::route('faq') }}" class="btn-outline w-full">
                        {{ __('feature.frequently_asked_questions') }} <x-ico name="arrow-right" class="h-4 w-4" />
                    </a>
                </div>
            </div>
        </aside>
    </div>
</section>
