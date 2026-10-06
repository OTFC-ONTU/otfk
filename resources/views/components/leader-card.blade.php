@props(['person'])

{{-- Картка керівника на сторінці адміністрації: фото в БД немає — тримається
     на монограмі, посаді та клікабельному телефоні. --}}
<div class="card group flex h-full flex-col p-5">
    <div class="flex items-start gap-4">
        @if ($person->photo)
            <x-picture :path="$person->photo" :alt="$person->localized('full_name')" loading="lazy" decoding="async"
                       class="h-14 w-14 shrink-0 rounded-full object-cover ring-2 ring-brand-50" />
        @else
            <span aria-hidden="true"
                  class="grid h-14 w-14 shrink-0 place-items-center rounded-full bg-brand-950 text-lg font-bold text-white ring-4 ring-brand-50">
                {{ $person->initials() ?: '—' }}
            </span>
        @endif

        <div class="min-w-0">
            <h3 class="text-base font-bold leading-snug text-brand-950">
                <a href="{{ \App\Support\LocalizedUrl::route('staff.show', $person) }}"
                   class="transition hover:text-brand-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-600 focus-visible:ring-offset-2">
                    {{ $person->localized('full_name') }}
                </a>
            </h3>
            @if ($person->localized('position'))
                <p class="mt-1 text-sm font-semibold leading-snug text-gold-700">{{ $person->localized('position') }}</p>
            @endif
            @if ($person->localized('academic_degree'))
                <p class="mt-1 text-xs text-slate-400">{{ $person->localized('academic_degree') }}</p>
            @endif
        </div>
    </div>

    <div class="mt-4 mb-4 space-y-2 text-sm">
        @if ($person->phone)
            <a href="tel:{{ preg_replace('/[^+\d]/', '', $person->phone) }}"
               class="inline-flex items-center gap-2 font-semibold text-brand-800 transition hover:text-brand-600">
                <x-ico name="phone" class="h-4 w-4 shrink-0 text-gold-600" aria-hidden="true" /> {{ $person->phone }}
            </a>
        @endif
        @if ($person->email)
            <a href="mailto:{{ $person->email }}"
               class="flex items-center gap-2 text-slate-600 transition hover:text-brand-700">
                <x-ico name="envelope" class="h-4 w-4 shrink-0 text-gold-600" aria-hidden="true" />
                <span class="break-all">{{ $person->email }}</span>
            </a>
        @endif
    </div>

    <div class="mt-auto flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
        @if ($person->department)
            <a href="{{ \App\Support\LocalizedUrl::route('structure.show', $person->department) }}"
               class="inline-flex max-w-full items-center gap-1.5 rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-800 ring-1 ring-brand-100 transition hover:bg-brand-100">
                <x-ico name="building-office-2" class="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
                <span class="min-w-0">{{ \Illuminate\Support\Str::limit($person->department->localized('title'), 42) }}</span>
            </a>
        @else
            <span></span>
        @endif

        <a href="{{ \App\Support\LocalizedUrl::route('staff.show', $person) }}"
           class="inline-flex shrink-0 items-center gap-1 text-xs font-semibold text-slate-400 transition group-hover:text-brand-700">
            {{ __('public.details') }} <x-ico name="arrow-right" class="h-3.5 w-3.5" aria-hidden="true" />
        </a>
    </div>
</div>
