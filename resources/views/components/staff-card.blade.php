@props(['person'])

<div class="card flex flex-col items-center p-6 text-center">
    @if ($person->photo)
        <x-picture :path="$person->photo" :alt="$person->localized('full_name')" loading="lazy" decoding="async"
                   class="h-24 w-24 rounded-full object-cover ring-4 ring-brand-50" />
    @else
        <span class="grid h-24 w-24 place-items-center rounded-full bg-gradient-to-br from-brand-600 to-brand-900 text-2xl font-bold text-white">
            {{ $person->initials() ?: '-' }}
        </span>
    @endif
    <p class="mt-4 font-bold text-slate-900">{{ $person->localized('full_name') }}</p>
    @if ($person->localized('position'))
        <p class="mt-1 text-sm font-medium text-brand-700">{{ $person->localized('position') }}</p>
    @endif
    @if ($person->localized('academic_degree'))
        <p class="mt-0.5 text-xs text-slate-400">{{ $person->localized('academic_degree') }}</p>
    @endif
    @if ($person->email || $person->phone)
        <div class="mt-3 space-y-1 text-xs text-slate-500">
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
</div>
