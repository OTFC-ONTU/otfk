@props(['person'])

@php
    // Сторінки викладача (як на оригіналі): опубліковані сторінки професійної діяльності та підвищення кваліфікації
    $profile = $person->relationLoaded('profilePage') ? $person->profilePage : null;
    $qualification = $person->relationLoaded('qualificationPage') ? $person->qualificationPage : null;
    $profile = $profile?->is_published ? $profile : null;
    $qualification = $qualification?->is_published ? $qualification : null;
    $mainUrl = ($profile ?? $qualification) ? \App\Support\LocalizedUrl::to('/' . ($profile ?? $qualification)->slug) : null;
@endphp

<div @class(['card relative flex flex-col items-center p-6 text-center', 'card-interactive' => $mainUrl])>
    @if ($person->photo)
        <x-picture :path="$person->photo" :alt="$person->localized('full_name')" loading="lazy" decoding="async"
                   class="h-24 w-24 rounded-full object-cover ring-4 ring-brand-50" />
    @else
        <span class="grid h-24 w-24 place-items-center rounded-full bg-gradient-to-br from-brand-600 to-brand-900 text-2xl font-bold text-white">
            {{ $person->initials() ?: '-' }}
        </span>
    @endif
    @if ($mainUrl)
        {{-- Уся картка клікабельна (розтягнуте посилання), додаткові посилання — над ним --}}
        <a href="{{ $mainUrl }}" class="mt-4 font-bold text-slate-900 after:absolute after:inset-0 after:rounded-2xl hover:text-brand-700">{{ $person->localized('full_name') }}</a>
    @else
        <p class="mt-4 font-bold text-slate-900">{{ $person->localized('full_name') }}</p>
    @endif
    @if ($person->localized('position'))
        <p class="mt-1 text-sm font-medium text-brand-700">{{ $person->localized('position') }}</p>
    @endif
    @if ($person->localized('academic_degree'))
        <p class="mt-0.5 text-xs text-slate-400">{{ $person->localized('academic_degree') }}</p>
    @endif
    @if ($person->email || $person->phone)
        <div class="relative z-10 mt-3 space-y-1 text-xs text-slate-500">
            @if ($person->email)
                <a href="mailto:{{ $person->email }}" class="flex items-center justify-center gap-1.5 hover:text-brand-700">
                    <x-ico name="envelope" class="h-4 w-4" /> {{ $person->email }}
                </a>
            @endif
            @if ($person->phone)
                <p class="flex items-center justify-center gap-1.5"><x-ico name="phone" class="h-4 w-4" /> {{ $person->phone }}</p>
            @endif
        </div>
    @endif
    @if ($profile || $qualification)
        <div class="relative z-10 mt-3 flex flex-wrap justify-center gap-x-3 gap-y-1 text-xs font-medium">
            @if ($profile)
                <a href="{{ \App\Support\LocalizedUrl::to('/' . $profile->slug) }}" class="text-brand-700 hover:underline">{{ __('public.staff_profile_page') }}</a>
            @endif
            @if ($qualification)
                <a href="{{ \App\Support\LocalizedUrl::to('/' . $qualification->slug) }}" class="text-brand-700 hover:underline">{{ __('public.staff_qualification_page') }}</a>
            @endif
        </div>
    @endif
</div>
