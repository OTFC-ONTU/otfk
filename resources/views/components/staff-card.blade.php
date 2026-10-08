@props(['person'])

{{-- Уся картка — одне посилання на сторінку співробітника; сторінки діяльності/кваліфікації — на ній --}}
<a href="{{ \App\Support\LocalizedUrl::route('staff.show', $person) }}"
   class="card card-interactive group flex flex-col items-center p-6 text-center">
    @if ($person->photo)
        <x-picture :path="$person->photo" :alt="$person->localized('full_name')" loading="lazy" decoding="async"
                   class="h-24 w-24 rounded-full object-cover ring-4 ring-brand-50" />
    @else
        <span class="grid h-24 w-24 place-items-center rounded-full bg-gradient-to-br from-brand-600 to-brand-900 text-2xl font-bold text-white">
            {{ $person->initials() ?: '-' }}
        </span>
    @endif
    <span class="mt-4 font-bold text-slate-900 group-hover:text-brand-700">{{ $person->localized('full_name') }}</span>
    @if ($person->localized('position'))
        <span class="mt-1 text-sm font-medium text-brand-700">{{ $person->localized('position') }}</span>
    @endif
    @if ($person->localized('academic_degree'))
        <span class="mt-0.5 text-xs text-slate-400">{{ $person->localized('academic_degree') }}</span>
    @endif
    @if ($person->email || $person->phone)
        <span class="mt-3 space-y-1 text-xs text-slate-500">
            @if ($person->email)
                <span class="flex items-center justify-center gap-1.5"><x-ico name="envelope" class="h-4 w-4" /> {{ $person->email }}</span>
            @endif
            @if ($person->phone)
                <span class="flex items-center justify-center gap-1.5"><x-ico name="phone" class="h-4 w-4" /> {{ $person->phone }}</span>
            @endif
        </span>
    @endif
    <span class="mt-auto inline-flex items-center gap-1 pt-4 text-xs font-semibold text-slate-400 transition group-hover:text-brand-700">
        {{ __('public.details') }} <x-ico name="arrow-right" class="h-3.5 w-3.5" />
    </span>
</a>
