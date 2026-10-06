@props(['person'])

<div class="card group flex flex-col items-center p-6 text-center transition hover:-translate-y-0.5 hover:shadow-lg">
    @if ($person->photo)
        <x-picture :path="$person->photo" :alt="$person->localized('full_name')" loading="lazy" decoding="async"
                   class="h-24 w-24 rounded-full object-cover ring-4 ring-brand-50" />
    @else
        <span class="grid h-24 w-24 place-items-center rounded-full bg-gradient-to-br from-brand-600 to-brand-900 text-2xl font-bold text-white">
            {{ $person->initials() ?: '-' }}
        </span>
    @endif
    <a href="{{ \App\Support\LocalizedUrl::route('staff.show', $person) }}" class="mt-4 font-bold text-slate-900 hover:text-brand-700">{{ $person->localized('full_name') }}</a>
    @if ($person->localized('position'))
        <p class="mt-1 text-sm font-medium text-brand-700">{{ $person->localized('position') }}</p>
    @endif
    @if ($person->localized('academic_degree'))
        <p class="mt-0.5 text-xs text-slate-400">{{ $person->localized('academic_degree') }}</p>
    @endif
    @if ($person->email || $person->phone)
        <div class="mt-3 space-y-1 text-xs text-slate-500">
            @if ($person->email)
                <p class="flex items-center justify-center gap-1.5"><x-ico name="envelope" class="h-4 w-4" /> {{ $person->email }}</p>
            @endif
            @if ($person->phone)
                <p class="flex items-center justify-center gap-1.5"><x-ico name="phone" class="h-4 w-4" /> {{ $person->phone }}</p>
            @endif
        </div>
    @endif
    @foreach (['profilePage' => 'staff_activity_page', 'qualificationPage' => 'staff_qualification_page'] as $relation => $label)
        @if ($person->$relation?->is_published)
            <a href="{{ \App\Support\LocalizedUrl::route('pages.show', $person->$relation) }}" class="mt-3 text-xs font-semibold text-brand-700 hover:underline">{{ __('public.'.$label) }}</a>
        @endif
    @endforeach
    <a href="{{ \App\Support\LocalizedUrl::route('staff.show', $person) }}" class="mt-4 inline-flex items-center gap-1 text-xs font-semibold text-slate-400 transition hover:text-brand-700">
        {{ __('public.details') }} <x-ico name="arrow-right" class="h-3.5 w-3.5" />
    </a>
</div>
