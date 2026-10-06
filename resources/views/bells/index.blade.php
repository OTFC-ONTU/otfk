<x-layouts.app title="{{ __('public.bells') }}" description="{{ __('feature.class_times_at_odesa_technical_professional_college') }}">

    @php
        $s = \App\Models\Setting::publicMap();

        $ord = [1 => '1-ша', 2 => '2-га', 3 => '3-тя', 4 => '4-та', 5 => '5-та', 6 => '6-та', 7 => '7-ма', 8 => '8-ма'];

        $hm = fn ($t) => substr((string) $t, 0, 5);
        $mins = fn ($a, $b) => (int) \Carbon\Carbon::parse($a)->diffInMinutes(\Carbon\Carbon::parse($b));

        // Українське відмінювання слова «пара» для лічильника
        $pairWord = function (int $n): string {
            $mod100 = $n % 100;
            $mod10 = $n % 10;

            if ($mod100 >= 11 && $mod100 <= 14) {
                return __('feature.classes_2');
            }

            return match (true) {
                $mod10 === 1 => __('feature.class'),
                $mod10 >= 2 && $mod10 <= 4 => __('feature.classes'),
                default => __('feature.classes_2'),
            };
        };

        // Пари згруповані по змінах; друга зміна ховається перемикачем в адмінці
        $shifts = $periods->groupBy(fn ($p) => (int) ($p->shift ?: 1));
        $twoShifts = $shifts->count() > 1;

        $lessonLength = $periods->isNotEmpty() ? $mins($periods->first()->starts, $periods->first()->ends) : null;

        // Дані для Alpine: номер, зміна, початок і кінець кожної пари
        $alpine = $periods->map(fn ($p) => [
            'id' => $p->id,
            'n' => (int) $p->number,
            'sh' => (int) ($p->shift ?: 1),
            's' => $hm($p->starts),
            'e' => $hm($p->ends),
        ])->values();
    @endphp

    {{-- Світла шапка розділу — у стилі новин, відео, галереї, спеціальностей і FAQ --}}
    <section class="border-b border-slate-200/70 bg-slate-50/80">
        <div class="container-site py-8 lg:py-10">
            <x-breadcrumbs tone="light" :items="[
                ['label' => __('public.home'), 'url' => \App\Support\LocalizedUrl::route('home')],
                ['label' => __('feature.students'), 'url' => \App\Support\LocalizedUrl::to('/studentu')],
                ['label' => __('public.bells')],
            ]" />

            <div class="relative mt-4 overflow-hidden rounded-2xl bg-white px-6 py-8 shadow-sm ring-1 ring-slate-200/80 sm:px-10 sm:py-10">
                {{-- Декоративний контур годинника — заповнює порожнечу праворуч на великих екранах --}}
                <x-ico name="clock" aria-hidden="true"
                       class="pointer-events-none absolute -right-8 top-1/2 hidden h-64 w-64 -translate-y-1/2 text-brand-50 lg:block" />

                <div class="relative max-w-3xl">
                    <h1 class="text-3xl font-extrabold leading-tight text-brand-950 sm:text-4xl lg:text-[2.75rem]">{{ __('public.bells') }}</h1>
                    <div class="accent-rule"></div>
                    <p class="mt-5 text-lg leading-relaxed text-slate-500">
                        {{ __('feature.class_and_break_times_the_current_class') }}
                    </p>

                    @if ($periods->isNotEmpty())
                        <div class="mt-5 flex flex-wrap items-center gap-2 text-sm">
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-gold-50 px-3 py-1 font-semibold text-gold-700 ring-1 ring-gold-300/70">
                                <x-ico name="bell-alert" class="h-4 w-4" />
                                {{ $periods->count() }} {{ $pairWord($periods->count()) }}
                            </span>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-50 px-3 py-1 font-semibold text-brand-800 ring-1 ring-brand-100">
                                <x-ico name="arrows-right-left" class="h-4 w-4" />
                                {{ $twoShifts ? __('feature.two_shifts') : __('feature.one_shift') }}
                            </span>
                            @if ($lessonLength)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-50 px-3 py-1 font-semibold text-brand-800 ring-1 ring-brand-100">
                                    <x-ico name="clock" class="h-4 w-4" />
                                    {{ __('public.class') }} — {{ __('feature.bell_minutes', ['minutes' => $lessonLength]) }}
                                </span>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <section class="container-site py-12"
             x-data="bellSchedule(@js($alpine))"
             x-init="tick(); setInterval(() => tick(), 15000)">

        @if ($periods->isEmpty())
            <x-empty-state icon="clock" title="{{ __('public.no_bells') }}" />
        @else
            {{-- Живий статус: що йде зараз або коли наступна пара --}}
            <div x-show="status" x-cloak
                 class="mx-auto mb-8 flex max-w-2xl items-center justify-center gap-2 rounded-2xl bg-brand-50 px-5 py-4 text-center font-semibold text-brand-800 ring-1 ring-brand-100">
                <span class="relative flex h-2.5 w-2.5 shrink-0">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-brand-400 opacity-75"></span>
                    <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-brand-600"></span>
                </span>
                <span x-text="status"></span>
            </div>

            <div class="{{ $twoShifts ? 'grid gap-6 lg:grid-cols-2' : 'mx-auto max-w-2xl' }}">
                @foreach ($shifts as $shift => $shiftPeriods)
                    @php
                        $shiftPeriods = $shiftPeriods->values();
                        $shiftStart = $hm($shiftPeriods->first()->starts);
                        $shiftEnd = $hm($shiftPeriods->last()->ends);
                    @endphp

                    <div class="card overflow-hidden">
                        {{-- Шапка зміни --}}
                        <div class="flex flex-wrap items-center justify-between gap-3 bg-brand-950 px-5 py-4 text-white sm:px-6">
                            <div class="flex items-center gap-3">
                                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-white/10 text-sm font-extrabold text-gold-300">{{ $shift }}</span>
                                <div>
                                    <p class="font-bold leading-tight">{{ __('feature.bell_shift', ['number' => $shift]) }}</p>
                                    <p class="text-xs text-brand-200">{{ $shiftPeriods->count() }} {{ $pairWord($shiftPeriods->count()) }}</p>
                                </div>
                            </div>
                            <span class="rounded-full bg-white/10 px-3 py-1 text-sm font-semibold tabular-nums text-gold-200">{{ $shiftStart }} – {{ $shiftEnd }}</span>
                        </div>

                        {{-- Пари та перерви між ними --}}
                        <ul class="divide-y divide-slate-100">
                            @php $latestEnd = null; @endphp
                            @foreach ($shiftPeriods as $i => $p)
                                @php
                                    $key = (string) $p->id;
                                    $next = $shiftPeriods->get($i + 1);
                                    $latestEnd = max($latestEnd ?? $p->ends, $p->ends);
                                    $gap = $next ? (int) \Carbon\Carbon::parse($latestEnd)->diffInMinutes(\Carbon\Carbon::parse($next->starts), false) : 0;

                                    // Золото на сторінці означає «просто зараз», тому перерва в спокої — сіра
                                    $gapIdle = $gap >= 20 ? 'bg-slate-50 font-semibold text-slate-500' : 'bg-slate-50 text-slate-400';
                                @endphp

                                <li class="relative px-5 py-4 transition-colors sm:px-6"
                                    :class="isNow('{{ $key }}') ? 'bg-gold-50/70' : ''">
                                    <div class="flex items-center gap-4">
                                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl text-base font-extrabold transition-colors"
                                              :class="isNow('{{ $key }}') ? 'bg-gold-400 text-brand-950' : 'bg-brand-50 text-brand-800'">{{ $p->number }}</span>

                                        <div class="min-w-0 flex-1">
                                            <p class="font-bold text-slate-900">{{ __('feature.bell_class', ['number' => app()->getLocale() === 'en' ? $p->number : ($ord[$p->number] ?? $p->number.'-та')]) }}</p>
                                            <p class="text-xs text-slate-400">{{ __('feature.bell_minutes', ['minutes' => $mins($p->starts, $p->ends)]) }}</p>
                                        </div>

                                        <div class="shrink-0 text-right">
                                            <p class="text-base font-bold tabular-nums text-slate-900">{{ $hm($p->starts) }} – {{ $hm($p->ends) }}</p>
                                            <p class="mt-0.5 h-5">
                                                <span x-show="isNow('{{ $key }}')" x-cloak class="badge bg-gold-100 text-gold-800">
                                                    {{ __('public.now') }} · <span x-text="left['{{ $key }}']"></span> {{ __('feature.minutes_short') }}
                                                </span>
                                            </p>
                                        </div>
                                    </div>

                                    {{-- Смужка проходження поточної пари --}}
                                    <span x-show="isNow('{{ $key }}')" x-cloak aria-hidden="true"
                                          class="absolute inset-x-0 bottom-0 h-1 bg-gold-100">
                                        <span class="block h-full bg-gold-400 transition-[width] duration-500"
                                              :style="`width: ${pct['{{ $key }}'] ?? 0}%`"></span>
                                    </span>
                                </li>

                                @if ($next && $gap > 0)
                                    {{-- Об'єктний синтаксис: спокійні класи стоять у розмітці (без миготіння до старту Alpine) і знімаються, коли перерва йде --}}
                                    <li class="px-5 py-2 text-center text-xs transition-colors sm:px-6 {{ $gapIdle }}"
                                        :class="{ '{{ $gapIdle }}': ! isGapNow('{{ $key }}'), 'bg-gold-50 font-semibold text-gold-700': isGapNow('{{ $key }}') }">
                                        {{ $gap >= 20 ? __('public.long_break') : __('public.break') }} · {{ __('feature.bell_minutes', ['minutes' => $gap]) }}
                                        <span x-show="isGapNow('{{ $key }}')" x-cloak>· {{ __('public.now') }}</span>
                                    </li>
                                @endif
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>

            <p class="mt-6 text-center text-sm text-slate-400">
                {{ __('feature.sunday_is_a_day_off_current_classes') }}
            </p>

            {{-- Фінальна смуга: куди звертатися щодо розкладу занять --}}
            <div class="mt-10 overflow-hidden rounded-2xl bg-gold-50 px-6 py-7 text-center ring-1 ring-gold-200 sm:px-10">
                <p class="text-lg font-bold text-brand-950">{{ __('feature.questions_about_the_timetable') }}</p>
                <p class="mx-auto mt-2 max-w-xl text-sm leading-relaxed text-slate-600">
                    {{ __('feature.contact_the_college_academic_office_for_class') }}
                </p>
                <div class="mt-5 flex flex-wrap items-center justify-center gap-3">
                    @if (! empty($s['contact_phone']))
                        <a href="tel:{{ preg_replace('/[^+\d]/', '', $s['contact_phone']) }}" class="btn btn-primary">
                            <x-ico name="phone" class="h-4 w-4" /> {{ $s['contact_phone'] }}
                        </a>
                    @endif
                    <a href="{{ \App\Support\LocalizedUrl::route('contacts') }}" class="btn btn-outline">{{ __('feature.college_contacts') }}</a>
                </div>
            </div>
        @endif
    </section>

</x-layouts.app>
