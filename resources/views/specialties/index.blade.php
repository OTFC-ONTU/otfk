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
                    <a href="{{ \App\Support\LocalizedUrl::route('specialties.show', $sp) }}" class="card card-interactive group flex flex-col overflow-hidden">
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
                            <h3 class="text-lg font-bold leading-snug text-slate-900 group-hover:text-brand-700">{{ $sp->localized('title') }}</h3>
                            @if ($sp->localized('degree'))
                                <p class="mt-1 text-sm font-medium text-gold-700">{{ $sp->localized('degree') }}</p>
                            @endif
                            <p class="mt-2 line-clamp-3 text-sm text-slate-500">{{ $sp->localized('short_description') }}</p>
                            <span class="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-700 transition group-hover:gap-2.5">
                                {{ __('public.details') }} <x-ico name="arrow-right" class="h-4 w-4" />
                            </span>
                        </div>
                    </a>
                @endforeach
            </div>
        @else
            <x-empty-state icon="academic-cap" :title="__('public.no_specialties')" />
        @endif
    </section>

</x-layouts.app>
