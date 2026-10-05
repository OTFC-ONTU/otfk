<x-layouts.app :title="__('public.specialties')">

    <x-page-hero :title="__('public.our_specialties')" :breadcrumbs="[
        ['label' => __('public.home'), 'url' => \App\Support\LocalizedUrl::route('home')],
        ['label' => __('public.specialties')],
    ]">
        <p class="mt-3 max-w-2xl text-brand-100">{{ __('public.specialties_intro') }}</p>
    </x-page-hero>

    <section class="container-site py-12">
        @if ($specialties->isNotEmpty())
            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($specialties as $sp)
                    @php $specialtyUrl = \App\Support\LocalizedUrl::route('specialties.show', $sp); @endphp
                    {{-- Уся картка веде на спеціальність (розтягнуте посилання), ОПП — окремі посилання поверх --}}
                    <article class="card card-interactive group relative flex flex-col overflow-hidden">
                        <div class="relative aspect-[16/9] overflow-hidden">
                            @if ($sp->cover_image)
                                <x-picture :path="$sp->cover_image" :alt="$sp->localized('title')" loading="lazy" decoding="async" class="h-full w-full object-cover transition duration-500 group-hover:scale-105" />
                            @else
                                <div class="flex h-full w-full items-center justify-center bg-gradient-to-br from-brand-700 to-brand-900 text-white/25">
                                    <x-ico name="academic-cap" class="h-14 w-14" />
                                </div>
                            @endif
                            @if ($sp->code)
                                <span class="absolute left-3 top-3 badge bg-white/90 text-brand-800">{{ __('public.code') }} {{ $sp->code }}</span>
                            @endif
                        </div>
                        <div class="flex flex-1 flex-col p-5">
                            <h3 class="text-lg font-bold leading-snug text-slate-900 group-hover:text-brand-700">
                                <a href="{{ $specialtyUrl }}" class="after:absolute after:inset-0 after:content-['']">{{ $sp->localized('title') }}</a>
                            </h3>
                            @if ($sp->localized('degree'))
                                <p class="mt-1 text-sm font-medium text-gold-700">{{ $sp->localized('degree') }}</p>
                            @endif
                            @if ($sp->localized('short_description'))
                                <p class="mt-2 line-clamp-3 text-sm text-slate-500">{{ $sp->localized('short_description') }}</p>
                            @endif

                            @if ($sp->programs->isNotEmpty())
                                <div class="relative z-10 mt-4 rounded-xl bg-brand-50/60 p-3 ring-1 ring-brand-100">
                                    <p class="flex items-center gap-1.5 px-1 text-[11px] font-semibold uppercase tracking-wide text-brand-700">
                                        <x-ico name="academic-cap" class="h-4 w-4" /> {{ __('public.programs') }}
                                    </p>
                                    <ul class="mt-2 space-y-1.5">
                                        @foreach ($sp->programs as $program)
                                            <li>
                                                @if ($program->file_url)
                                                    <a href="{{ $program->file_url }}" target="_blank" rel="noopener"
                                                       class="flex items-start gap-2.5 rounded-lg bg-white px-2.5 py-2 text-sm leading-snug text-slate-700 shadow-sm ring-1 ring-slate-200/70 transition hover:text-brand-800 hover:ring-brand-300">
                                                        <span class="mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-md bg-rose-50 text-rose-600"><x-ico name="document-text" class="h-4 w-4" /></span>
                                                        <span class="flex-1 font-medium">{{ $program->localized('title') }}</span>
                                                        <x-ico name="arrow-down-tray" class="mt-0.5 h-4 w-4 shrink-0 text-slate-400" />
                                                    </a>
                                                @else
                                                    <span class="flex items-start gap-2.5 rounded-lg bg-white px-2.5 py-2 text-sm leading-snug text-slate-700 ring-1 ring-slate-200/70">
                                                        <span class="mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-md bg-brand-50 text-brand-600"><x-ico name="book-open" class="h-4 w-4" /></span>
                                                        <span class="flex-1 font-medium">{{ $program->localized('title') }}</span>
                                                    </span>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <span class="mt-auto inline-flex items-center gap-1.5 pt-4 text-sm font-semibold text-brand-700 transition group-hover:gap-2.5">
                                {{ __('public.details') }} <x-ico name="arrow-right" class="h-4 w-4" />
                            </span>
                        </div>
                    </article>
                @endforeach
            </div>
        @else
            <x-empty-state icon="academic-cap" :title="__('public.no_specialties')" />
        @endif
    </section>

</x-layouts.app>
