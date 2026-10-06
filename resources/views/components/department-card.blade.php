@props(['department'])

{{-- Картка структурного підрозділу: обкладинок у базі немає, тож акцент —
     іконка типу, назва та короткий уривок опису. Використовується списком
     структури та блоком «Інші підрозділи» на детальній сторінці. --}}
@php
    // Іконка групи: типів рівно три (Department::TYPES), решта — запасний варіант
    $icon = match ($department->type) {
        'viddilennya' => 'building-office-2',
        'tsyklova-komisiya' => 'user-group',
        'kafedra' => 'academic-cap',
        default => 'building-office-2',
    };

    // Опис підрозділів — імпортований HTML, у картку йде короткий уривок
    $excerpt = \Illuminate\Support\Str::limit(
        trim(preg_replace('/\s+/u', ' ', strip_tags((string) $department->localized('description')))),
        130
    );

    $staffCount = $department->staff_count ?? null;

    $staffWord = function (int $n): string {
        $mod100 = $n % 100;
        $mod10 = $n % 10;

        if ($mod100 >= 11 && $mod100 <= 14) {
            return __('feature.staff_members');
        }

        return match (true) {
            $mod10 === 1 => __('feature.staff_member'),
            $mod10 >= 2 && $mod10 <= 4 => __('feature.staff_members_2'),
            default => __('feature.staff_members'),
        };
    };
@endphp

<article {{ $attributes->class(['card card-interactive group relative flex flex-col p-6']) }}>
    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-700 transition group-hover:bg-brand-950 group-hover:text-gold-400">
        <x-ico :name="$icon" class="h-6 w-6" aria-hidden="true" />
    </span>

    <h3 class="mt-4 font-bold leading-snug text-brand-950 group-hover:text-brand-700">
        <a href="{{ \App\Support\LocalizedUrl::route('structure.show', $department) }}" class="after:absolute after:inset-0 focus:outline-none focus-visible:underline">{{ $department->localized('title') }}</a>
    </h3>

    @if ($excerpt)
        <p class="mt-3 line-clamp-3 text-sm leading-relaxed text-slate-500">{{ $excerpt }}</p>
    @endif

    <div class="mt-5 flex flex-1 flex-wrap items-end justify-between gap-x-3 gap-y-2 border-t border-slate-100 pt-4 text-sm">
        @if ($staffCount)
            <span class="inline-flex items-center gap-1.5 whitespace-nowrap text-slate-500">
                <x-ico name="users" class="h-4 w-4 text-brand-500" aria-hidden="true" />
                {{ $staffCount }} {{ $staffWord($staffCount) }}
            </span>
        @else
            <span></span>
        @endif
        <span class="inline-flex items-center gap-1 whitespace-nowrap font-semibold text-gold-700 transition group-hover:gap-2">
            {{ __('public.details') }} <x-ico name="arrow-right" class="h-4 w-4" aria-hidden="true" />
        </span>
    </div>
</article>
