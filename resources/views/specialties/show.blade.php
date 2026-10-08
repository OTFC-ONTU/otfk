<x-layouts.app :title="__('feature.meta_specialty_title', ['title' => $specialty->localized('title')])"
               :description="\App\Support\MetaText::from($specialty->localized('short_description'), $specialty->localized('description'), __('feature.meta_specialty_description', ['title' => $specialty->localized('title')]))"
               :og-image="$specialty->cover_image ? asset('storage/' . $specialty->cover_image) : null">

    @if (! empty($adminPreview))
        <x-draft-notice message="{{ __('feature.preview_of_this_specialty_changes_are_unsaved') }}" />
    @elseif (! $specialty->is_published)
        <x-draft-notice />
    @endif

    {{-- Розмітка Course для пошукових систем --}}
    @php
        $courseLd = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Course',
            'name' => $specialty->localized('title'),
            'description' => $specialty->localized('short_description') ?: __('feature.specialty_schema', ['title' => $specialty->localized('title'), 'brand' => \App\Models\Setting::publicGet('brand_name') ?: __('layout.brand_name')]),
            'courseCode' => $specialty->code,
            'url' => \App\Support\LocalizedUrl::route('specialties.show', $specialty),
            'provider' => \App\Support\StructuredData::reference(),
        ]);

        $facts = array_filter([
            ['academic-cap', $specialty->localized('degree')],
            ['calendar-days', $specialty->localized('study_form')],
            ['clock', $specialty->localized('duration')],
        ], fn ($r) => filled($r[1]));
    @endphp
    <script type="application/ld+json">{!! json_encode($courseLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>

    {{-- Шапка: обкладинок у базі немає, тож праворуч — гігантський код спеціальності та іконка напряму --}}
    <section class="relative overflow-hidden bg-brand-950">
        <svg aria-hidden="true" class="pointer-events-none absolute inset-0 h-full w-full text-white/[0.06]">
            <defs>
                <pattern id="sp-hero-grid" width="34" height="34" patternUnits="userSpaceOnUse">
                    <path d="M34 0H0V34" fill="none" stroke="currentColor" stroke-width="1" />
                </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#sp-hero-grid)" />
        </svg>

        {{-- Код і іконка живуть у потоці на мобільному та йдуть праворуч на широких екранах --}}
        <div class="pointer-events-none absolute inset-y-0 right-0 hidden items-center gap-8 pr-8 lg:flex 2xl:pr-16">
            @if ($specialty->code)
                <span class="font-display text-[9rem] font-extrabold leading-none text-white/10 2xl:text-[11rem]">{{ $specialty->code }}</span>
            @endif
            <x-ico :name="$specialty->icon_name" aria-hidden="true" class="h-32 w-32 text-gold-400/80 2xl:h-40 2xl:w-40" />
        </div>

        <div class="container-site relative py-12 lg:py-16">
            <x-breadcrumbs :items="[
                ['label' => __('public.home'), 'url' => \App\Support\LocalizedUrl::route('home')],
                ['label' => __('public.specialties'), 'url' => \App\Support\LocalizedUrl::route('specialties.index')],
                ['label' => $specialty->localized('title')],
            ]" />

            <div class="max-w-3xl lg:max-w-2xl xl:max-w-3xl">
                @if ($specialty->code)
                    <span class="badge mt-5 bg-white/10 text-brand-100 ring-1 ring-white/15">{{ __('feature.specialty_code_label', ['code' => $specialty->code]) }}</span>
                @endif

                <h1 class="mt-4 text-3xl font-extrabold leading-tight text-white sm:text-4xl lg:text-[2.75rem]">{{ $specialty->localized('title') }}</h1>

                @if ($specialty->localized('short_description'))
                    <p class="mt-4 text-lg leading-relaxed text-brand-100">{{ $specialty->localized('short_description') }}</p>
                @endif

                @if ($facts)
                    <div class="mt-6 flex flex-wrap gap-2.5">
                        @foreach ($facts as [$icon, $value])
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1.5 text-sm font-medium text-brand-100 ring-1 ring-white/15">
                                <x-ico :name="$icon" class="h-4 w-4 text-gold-300" aria-hidden="true" /> {{ $value }}
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </section>

    <section class="container-site grid gap-10 py-12 lg:grid-cols-12">
        <div class="lg:col-span-8">
            @if ($specialty->cover_image)
                <x-picture :path="$specialty->cover_image" :alt="$specialty->localized('title')" sized decoding="async" class="mb-8 w-full rounded-2xl object-cover" />
            @endif

            {{-- «Про спеціальність»: короткий опис винесено в помітну картку над основним текстом --}}
            @if ($specialty->localized('short_description'))
                <div class="card flex gap-4 bg-gradient-to-br from-gold-50 to-white p-6 ring-gold-200/70">
                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-gold-100 text-gold-700">
                        <x-ico name="book-open" class="h-6 w-6" aria-hidden="true" />
                    </span>
                    <div>
                        <h2 class="font-bold text-slate-900">{{ __('public.about_specialty') }}</h2>
                        <p class="mt-2 leading-relaxed text-slate-600">{{ $specialty->localized('short_description') }}</p>
                    </div>
                </div>
            @endif

            @if (filled($specialty->localized('description')))
                <div @class([
                    'prose prose-slate max-w-none prose-headings:font-display prose-a:text-brand-700',
                    'mt-8' => filled($specialty->localized('short_description')),
                ])>{!! \App\Support\LazyMedia::render(\App\Support\FileCards::render(\App\Support\ResponsiveTables::render(\App\Support\LocalizedHtml::links($specialty->localized('description')))), false) !!}</div>
            @endif

            {{-- Освітні програми --}}
            @if ($specialty->programs->isNotEmpty())
                <div class="mt-10">
                    <h2 class="text-xl font-bold text-slate-900">{{ __('public.programs') }}</h2>
                    <div class="accent-rule"></div>
                    <ul class="mt-5 space-y-3">
                        @foreach ($specialty->programs as $program)
                            <li><x-file-card :href="$program->file_url" :download="(bool) $program->file_path && !$program->external_url" :title="$program->localized('title')" :description="$program->localized('description')" :extension="$program->file_url ? pathinfo(parse_url($program->file_url, PHP_URL_PATH), PATHINFO_EXTENSION) : ''" /></li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Наступний крок шляху «спеціальність → умови вступу → контакти» (docs/seo-plan.md, етап 2) --}}
            <nav class="mt-10" aria-labelledby="specialty-next-step" data-next-step>
                <h2 id="specialty-next-step" class="text-xl font-bold text-slate-900">{{ __('feature.specialty_next_step') }}</h2>
                <div class="accent-rule"></div>
                <p class="mt-4 text-slate-600">{{ __('feature.specialty_next_step_text') }}</p>
                <ol class="mt-5 grid gap-4 sm:grid-cols-2">
                    <li>
                        <a href="{{ \App\Support\LocalizedUrl::to('/abituriyentu') }}" class="card group flex h-full gap-3 p-5 transition hover:ring-brand-300">
                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-brand-50 text-brand-700">
                                <x-ico name="academic-cap" class="h-5 w-5" aria-hidden="true" />
                            </span>
                            <span>
                                <span class="block font-semibold text-slate-900 group-hover:text-brand-700">{{ __('feature.specialty_next_admission') }}</span>
                                <span class="mt-1 block text-sm text-slate-600">{{ __('feature.specialty_next_admission_text') }}</span>
                            </span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ \App\Support\LocalizedUrl::route('contacts') }}" class="card group flex h-full gap-3 p-5 transition hover:ring-brand-300">
                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-brand-50 text-brand-700">
                                <x-ico name="map-pin" class="h-5 w-5" aria-hidden="true" />
                            </span>
                            <span>
                                <span class="block font-semibold text-slate-900 group-hover:text-brand-700">{{ __('feature.specialty_next_contacts') }}</span>
                                <span class="mt-1 block text-sm text-slate-600">{{ __('feature.specialty_next_contacts_text') }}</span>
                            </span>
                        </a>
                    </li>
                </ol>
            </nav>

            {{-- Золота смуга з виходом на приймальну комісію --}}
            <div class="mt-10 flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-gold-50 px-6 py-5 ring-1 ring-gold-200/80">
                <div class="flex items-start gap-3">
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-gold-100 text-gold-700">
                        <x-ico name="light-bulb" class="h-5 w-5" aria-hidden="true" />
                    </span>
                    <div>
                        <p class="font-bold text-slate-900">{{ __('feature.need_advice') }}</p>
                        <p class="text-sm text-slate-600">{{ __('feature.our_staff_can_answer_your_questions_about') }}</p>
                    </div>
                </div>
                <a href="{{ \App\Support\LocalizedUrl::route('contacts') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-gold-700 transition hover:gap-2.5">
                    {{ __('feature.contact_the_admissions_office_3') }} <x-ico name="arrow-right" class="h-4 w-4" />
                </a>
            </div>

            <a href="{{ \App\Support\LocalizedUrl::route('specialties.index') }}" class="btn-outline mt-10"><x-ico name="arrow-left" class="h-4 w-4" /> {{ __('public.back_specialties') }}</a>
        </div>

        <aside class="lg:col-span-4">
            <div class="card p-6 lg:sticky lg:top-28">
                <h2 class="font-bold text-slate-900">{{ __('public.study_details') }}</h2>
                <div class="accent-rule"></div>
                <dl class="mt-4 divide-y divide-slate-100 text-sm">
                    @foreach (array_filter([
                        __('feature.specialty_code') => $specialty->code,
                        __('public.degree') => $specialty->localized('degree'),
                        __('public.study_form') => $specialty->localized('study_form'),
                        __('public.duration') => $specialty->localized('duration'),
                    ]) as $label => $value)
                        <div class="flex justify-between gap-3 py-3">
                            <dt class="shrink-0 text-slate-500">{{ $label }}</dt>
                            <dd class="text-right font-semibold text-slate-800">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>

                <a href="{{ \App\Support\LocalizedUrl::route('contacts') }}" class="btn-primary mt-5 w-full">
                    {{ __('feature.contact_the_admissions_office_2') }} <x-ico name="arrow-right" class="h-4 w-4" />
                </a>
                <a href="{{ \App\Support\LocalizedUrl::route('quiz') }}" class="btn-outline mt-3 w-full border-gold-300 text-gold-700 ring-gold-300 hover:bg-gold-50">
                    {{ __('feature.take_the_specialty_quiz') }} <x-ico name="arrow-right" class="h-4 w-4" />
                </a>
            </div>
        </aside>
    </section>

    {{-- Інші спеціальності — тими самими картками, що й у списку --}}
    @if ($others->isNotEmpty())
        <section class="border-t border-slate-200/70 bg-slate-50/80 py-12">
            <div class="container-site">
                <h2 class="text-2xl font-extrabold text-brand-950">{{ __('public.other_specialties') }}</h2>
                <div class="accent-rule"></div>

                <div class="mt-8 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($others as $other)
                        <x-specialty-card :specialty="$other" :show-program="false" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

</x-layouts.app>
