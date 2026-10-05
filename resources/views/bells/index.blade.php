<x-layouts.app :title="__('public.bells')" :description="__('public.bells_description')">

    <x-page-hero :title="__('public.bells')" :breadcrumbs="[
        ['label' => __('public.home'), 'url' => \App\Support\LocalizedUrl::route('home')],
        ['label' => __('public.bells')],
    ]" />

    <section class="container-site py-12"
             x-data="bellSchedule(@js($periods->map(fn ($b) => ['n' => $b->number, 's' => substr($b->starts, 0, 5), 'e' => substr($b->ends, 0, 5)])->values()))"
             x-init="tick(); setInterval(() => tick(), 15000)">

        @if ($periods->isEmpty())
            <x-empty-state icon="clock" :title="__('public.no_bells')" />
        @else
            {{-- Живий статус --}}
            <div class="mx-auto max-w-2xl">
                <div x-show="status" x-cloak
                     class="mb-6 flex items-center justify-center gap-2 rounded-2xl bg-brand-50 px-5 py-4 text-center font-semibold text-brand-800 ring-1 ring-brand-100">
                    <span class="relative flex h-2.5 w-2.5">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-brand-400 opacity-75"></span>
                        <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-brand-600"></span>
                    </span>
                    <span x-text="status"></span>
                </div>

                <div class="card overflow-hidden">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-brand-950 text-left text-xs uppercase tracking-wide text-brand-200">
                                <th class="px-5 py-3.5 font-semibold">{{ __('public.class') }}</th>
                                <th class="px-5 py-3.5 font-semibold">{{ __('public.start') }}</th>
                                <th class="px-5 py-3.5 font-semibold">{{ __('public.end') }}</th>
                                <th class="px-5 py-3.5 text-right font-semibold">{{ __('public.status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($periods->values() as $i => $p)
                                <tr :class="current === {{ $p->number }} ? 'bg-gold-50/70' : ''">
                                    <td class="px-5 py-3.5 font-bold text-slate-900">{{ __('public.class_number', ['number' => $p->number, 'suffix' => [1 => 'ша', 2 => 'га', 3 => 'тя', 4 => 'та', 5 => 'та', 6 => 'та', 7 => 'ма', 8 => 'ма'][$p->number] ?? 'та']) }}</td>
                                    <td class="px-5 py-3.5 tabular-nums text-slate-600">{{ substr($p->starts, 0, 5) }}</td>
                                    <td class="px-5 py-3.5 tabular-nums text-slate-600">{{ substr($p->ends, 0, 5) }}</td>
                                    <td class="px-5 py-3.5 text-right">
                                        <span x-show="current === {{ $p->number }}" x-cloak class="badge bg-gold-100 text-gold-800">{{ __('public.now') }}</span>
                                        <span x-show="current !== {{ $p->number }}" class="text-slate-300">—</span>
                                    </td>
                                </tr>
                                @php
                                    $next = $periods->values()->get($i + 1);
                                    $gap = $next ? \Carbon\Carbon::parse($p->ends)->diffInMinutes(\Carbon\Carbon::parse($next->starts)) : 0;
                                @endphp
                                @if ($next && $gap > 0)
                                    <tr>
                                        <td colspan="4" class="px-5 py-2 text-center text-xs {{ $gap >= 20 ? 'bg-gold-50 font-semibold text-gold-700' : 'bg-slate-50 text-slate-400' }}">
                                            {{ $gap >= 20 ? __('public.long_break') : __('public.break') }} · {{ __('public.minutes', ['minutes' => $gap]) }}
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <p class="mt-5 text-center text-sm text-slate-400">
                    {{ __('public.class_duration', ['minutes' => $periods->first() ? \Carbon\Carbon::parse($periods->first()->starts)->diffInMinutes(\Carbon\Carbon::parse($periods->first()->ends)) : 80]) }}
                </p>
            </div>
        @endif
    </section>

</x-layouts.app>
