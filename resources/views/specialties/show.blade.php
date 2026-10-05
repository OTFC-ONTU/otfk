<x-layouts.app :title="$specialty->localized('title')" :description="$specialty->localized('short_description')"
               :og-image="$specialty->cover_image ? asset('storage/' . $specialty->cover_image) : null">

    {{-- Розмітка Course для пошукових систем --}}
    @php
        $courseLd = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Course',
            'name' => $specialty->localized('title'),
            'description' => $specialty->localized('short_description') ?: __('public.specialty_description', ['title' => $specialty->localized('title'), 'college' => config('app.name')]),
            'courseCode' => $specialty->code,
            'url' => \App\Support\LocalizedUrl::route('specialties.show', $specialty),
            'provider' => ['@type' => 'CollegeOrUniversity', 'name' => config('app.name'), 'url' => \App\Support\LocalizedUrl::to('/')],
        ]);
    @endphp
    <script type="application/ld+json">{!! json_encode($courseLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>

    <section class="bg-brand-950">
        <div class="container-site py-12 lg:py-14">
            <x-breadcrumbs :items="[
                ['label' => __('public.home'), 'url' => \App\Support\LocalizedUrl::route('home')],
                ['label' => __('public.specialties'), 'url' => \App\Support\LocalizedUrl::route('specialties.index')],
                ['label' => $specialty->localized('title')],
            ]" />
            @if ($specialty->code)
                <span class="mt-4 inline-block badge bg-white/10 text-brand-100 ring-1 ring-white/15">{{ __('public.specialty_code', ['code' => $specialty->code]) }}</span>
            @endif
            <h1 class="mt-3 max-w-4xl text-3xl font-extrabold leading-tight text-white sm:text-4xl">{{ $specialty->localized('title') }}</h1>
            <div class="mt-4 flex flex-wrap gap-2.5">
                @foreach (array_filter([
                    ['academic-cap', $specialty->localized('degree')],
                    ['clock', $specialty->localized('duration')],
                    ['user-group', $specialty->localized('study_form')],
                ], fn ($r) => ! empty($r[1])) as [$icon, $value])
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1.5 text-xs font-medium text-brand-100 ring-1 ring-white/15">
                        <x-ico :name="$icon" class="h-3.5 w-3.5 text-gold-300" /> {{ $value }}
                    </span>
                @endforeach
            </div>
        </div>
    </section>

    <section class="container-site grid gap-10 py-12 lg:grid-cols-12">
        <div class="lg:col-span-8">
            @if ($specialty->cover_image)
                <x-picture :path="$specialty->cover_image" :alt="$specialty->localized('title')" loading="lazy" decoding="async" class="mb-8 w-full rounded-2xl object-cover" />
            @endif
            @if ($specialty->localized('short_description'))
                <p class="mb-6 text-lg font-medium leading-relaxed text-slate-600">{{ $specialty->localized('short_description') }}</p>
            @endif
            @if (filled($specialty->localized('description')))
                <div class="prose prose-slate max-w-none prose-headings:font-display prose-a:text-brand-700">{!! \App\Support\LocalizedHtml::links($specialty->localized('description')) !!}</div>
            @endif

            {{-- Освітні програми --}}
            @if ($specialty->programs->isNotEmpty())
                <div class="mt-10">
                    <h2 class="text-xl font-bold text-slate-900">{{ __('public.programs') }}</h2>
                    <div class="accent-rule"></div>
                    <ul class="mt-5 space-y-3">
                        @foreach ($specialty->programs as $program)
                            <li class="card flex items-center gap-4 p-4">
                                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-700"><x-ico name="document-text" class="h-6 w-6" /></span>
                                <div class="min-w-0 flex-1">
                                    <p class="font-medium text-slate-800">{{ $program->localized('title') }}</p>
                                    @if ($program->localized('description'))<p class="text-sm text-slate-500">{{ $program->localized('description') }}</p>@endif
                                </div>
                                @if ($program->file_url)
                                    <a href="{{ $program->file_url }}" target="_blank" rel="noopener" class="btn-outline shrink-0 px-3 py-2 text-xs">
                                        <x-ico name="arrow-down-tray" class="h-4 w-4" /> {{ __('public.download') }}
                                    </a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <a href="{{ \App\Support\LocalizedUrl::route('specialties.index') }}" class="btn-outline mt-10"><x-ico name="arrow-left" class="h-4 w-4" /> {{ __('public.back_specialties') }}</a>
        </div>

        <aside class="lg:col-span-4">
            <div class="space-y-6 lg:sticky lg:top-28">
                <div class="card p-6">
                    <h2 class="font-bold text-slate-900">{{ __('public.study_details') }}</h2>
                    <div class="accent-rule"></div>
                    <dl class="mt-4 divide-y divide-slate-100 text-sm">
                        @foreach (array_filter([
                            __('public.degree') => $specialty->localized('degree'),
                            __('public.study_form') => $specialty->localized('study_form'),
                            __('public.duration') => $specialty->localized('duration'),
                            __('public.code') => $specialty->code,
                        ]) as $label => $value)
                            <div class="flex justify-between gap-3 py-3">
                                <dt class="text-slate-500">{{ $label }}</dt>
                                <dd class="text-right font-semibold text-slate-800">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                    <a href="{{ \App\Support\LocalizedUrl::to('/abituriyentu') }}" class="btn-primary mt-5 w-full">{{ __('public.how_to_apply') }}</a>
                </div>

                @if ($others->isNotEmpty())
                    <div class="card p-6">
                        <h2 class="font-bold text-slate-900">{{ __('public.other_specialties') }}</h2>
                        <ul class="mt-4 space-y-2">
                            @foreach ($others as $o)
                                <li>
                                    <a href="{{ \App\Support\LocalizedUrl::route('specialties.show', $o) }}" class="group flex items-center gap-2 rounded-lg p-2 transition hover:bg-slate-50">
                                        <x-ico name="academic-cap" class="h-5 w-5 shrink-0 text-brand-500" />
                                        <span class="min-w-0 flex-1 truncate text-sm font-medium text-slate-700 group-hover:text-brand-700">{{ $o->localized('title') }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </aside>
    </section>

</x-layouts.app>
