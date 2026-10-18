@php
    $tokens = fn (int $value) => number_format($value, 0, ',', ' ');
    $usd = fn (?float $value) => $value === null ? '—' : ($value == 0 ? '$0' : '$'.number_format($value, $value < 1 ? 4 : 2, '.', ' '));
    $statusLabels = [
        'pending' => ['У черзі', 'text-gray-600 dark:text-gray-300'],
        'processing' => ['Перекладається', 'text-primary-600 dark:text-primary-400'],
        'done' => ['Готово', 'text-success-600 dark:text-success-400'],
        'failed' => ['Помилка', 'text-danger-600 dark:text-danger-400'],
        'skipped' => ['Пропущено', 'text-gray-500 dark:text-gray-400'],
        'retried' => ['Повторено', 'text-gray-500 dark:text-gray-400'],
    ];

    // Графіки малюються на сервері (div/SVG без JS): працюють і після Livewire-оновлення сторінки.
    // Якщо ціна моделі невідома (вартість 0), стовпчики показують кількість запитів.
    $byCost = collect($stats['daily'])->sum('cost') > 0;
    $dailyValue = fn (array $day) => $byCost ? $day['cost'] : $day['requests'];
    $dailyMax = max(collect($stats['daily'])->map($dailyValue)->max(), $byCost ? 0.0001 : 1);
    $monthlyMax = max(collect($stats['monthly'])->max('cost'), 0.0001);
    $monthlyRequestsMax = max(collect($stats['monthly'])->max('requests'), 1);
    $monthlyByCost = collect($stats['monthly'])->sum('cost') > 0;
    $typeMax = max(collect($stats['by_type'])->max($monthlyByCost ? 'cost' : 'requests') ?? 0, 0.0001);
    $height = fn (float $value, float $max) => $value > 0 ? max(4, round($value / $max * 100, 1)) : 0;

    $donut = collect([
        ['Готово', $stats['done'], 'var(--success-500)'],
        ['У черзі', $stats['pending'], 'var(--primary-500)'],
        ['Помилки', $stats['failed'], 'var(--danger-500)'],
        ['Пропущено', $stats['skipped'], 'var(--gray-400)'],
    ])->filter(fn (array $slice) => $slice[1] > 0)->values();
    $donutTotal = $donut->sum(1);
    $successRate = $stats['done'] + $stats['failed'] > 0 ? round($stats['done'] / ($stats['done'] + $stats['failed']) * 100) : null;
    $budgetShare = $stats['budget'] ? min(100, round($stats['cost'] / $stats['budget'] * 100, 1)) : null;
@endphp

{{-- grid-cols-1 (minmax(0, 1fr)), а не голий grid: інакше широкий вміст розтягує колонку ширше за екран телефона. --}}
<div class="grid min-w-0 grid-cols-1 gap-6">
<x-filament::section>
    <x-slot name="heading">Використання API — {{ $stats['month_label'] }}</x-slot>
    <x-slot name="description">
        @if (! $stats['has_key'])
            Ключ API не збережено — автопереклад не працює.
        @elseif (! $stats['enabled'])
            Автопереклад вимкнено.
        @else
            Автопереклад увімкнено, модель <span class="break-all">{{ $stats['model'] }}</span>@if ($stats['model_price']) ({{ $stats['model_price'] }})@endif.
        @endif
    </x-slot>

    <div class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
        <div class="min-w-0 rounded-xl border border-gray-200 p-3 sm:p-4 dark:border-white/10">
            <div class="text-sm text-gray-500 dark:text-gray-400">Приблизна вартість</div>
            <div class="mt-1 text-xl font-semibold text-gray-950 sm:text-2xl dark:text-white">{{ $usd($stats['cost']) }}</div>
            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Минулий місяць: {{ $usd($stats['previous_cost']) }}
                @if ($stats['budget'])
                    · ліміт {{ $usd($stats['budget']) }}
                @endif
            </div>
            @if ($budgetShare !== null)
                <div class="mt-3 h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10" title="Використано {{ $budgetShare }}% місячного ліміту">
                    <div class="h-full rounded-full" style="width: {{ $budgetShare }}%; background: {{ $budgetShare >= 90 ? 'var(--danger-500)' : ($budgetShare >= 70 ? 'var(--warning-500)' : 'var(--primary-500)') }}"></div>
                </div>
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $budgetShare }}% ліміту</div>
            @endif
        </div>
        <div class="min-w-0 rounded-xl border border-gray-200 p-3 sm:p-4 dark:border-white/10">
            <div class="text-sm text-gray-500 dark:text-gray-400">Вхідні токени</div>
            <div class="mt-1 text-xl font-semibold text-gray-950 sm:text-2xl dark:text-white">{{ $tokens($stats['input']) }}</div>
            @if ($stats['cache'])
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">+ кеш: {{ $tokens($stats['cache']) }}</div>
            @endif
        </div>
        <div class="min-w-0 rounded-xl border border-gray-200 p-3 sm:p-4 dark:border-white/10">
            <div class="text-sm text-gray-500 dark:text-gray-400">Вихідні токени</div>
            <div class="mt-1 text-xl font-semibold text-gray-950 sm:text-2xl dark:text-white">{{ $tokens($stats['output']) }}</div>
            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Разом із «міркуваннями» моделі</div>
        </div>
        <div class="min-w-0 rounded-xl border border-gray-200 p-3 sm:p-4 dark:border-white/10">
            <div class="text-sm text-gray-500 dark:text-gray-400">Запитів за місяць</div>
            <div class="mt-1 text-xl font-semibold text-gray-950 sm:text-2xl dark:text-white">{{ $tokens($stats['requests']) }}</div>
            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                У черзі: {{ $stats['pending'] }} · помилок: {{ $stats['failed'] }}
            </div>
        </div>
    </div>

    @if ($stats['price_unknown'])
        <p class="mt-4 text-sm font-medium text-warning-600 dark:text-warning-400">
            Ціна вибраної моделі невідома (немає в config/auto_translate.php) — вартість не оцінюється@if ($stats['budget']), а з місячним лімітом переклад не запускається@endif.
        </p>
    @endif

    @if ($stats['budget_exceeded'])
        <p class="mt-4 text-sm font-medium text-danger-600 dark:text-danger-400">
            Місячний ліміт вичерпано — черга чекає наступного місяця або збільшення ліміту.
        </p>
    @endif

    @if (count($stats['by_model']) > 1)
        <div class="mt-6 overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="text-xs uppercase text-gray-500 dark:text-gray-400">
                    <tr>
                        <th class="py-2 pr-4 font-medium">Модель</th>
                        <th class="py-2 pr-4 text-right font-medium">Запитів</th>
                        <th class="py-2 pr-4 text-right font-medium">Вхід</th>
                        <th class="py-2 pr-4 text-right font-medium">Вихід</th>
                        <th class="py-2 text-right font-medium">Вартість</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($stats['by_model'] as $row)
                        <tr>
                            <td class="py-2 pr-4">{{ $row['model'] ?: '—' }}</td>
                            <td class="py-2 pr-4 text-right">{{ $tokens($row['requests']) }}</td>
                            <td class="py-2 pr-4 text-right">{{ $tokens($row['input']) }}</td>
                            <td class="py-2 pr-4 text-right">{{ $tokens($row['output']) }}</td>
                            <td class="py-2 text-right">{{ $usd($row['cost']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
        Вартість оцінюється на сайті за кількістю токенів у відповідях API і довідковими цінами (config/auto_translate.php);
        точну суму показує рахунок у console.anthropic.com. Місяць — за київським часом.
    </p>
</x-filament::section>

<x-filament::section collapsible>
    <x-slot name="heading">{{ $byCost ? 'Витрати' : 'Запити' }} за 30 днів</x-slot>
    <x-slot name="description">
        Разом: {{ $usd(collect($stats['daily'])->sum('cost')) }} · {{ $tokens(collect($stats['daily'])->sum('requests')) }} запитів
    </x-slot>

    @php($dailyHint = 'Наведіть або торкніться стовпчика, щоб побачити день.')
    <div x-data="{ tip: '' }">
        <div class="flex gap-2">
            <div class="flex w-12 shrink-0 flex-col justify-between pb-5 text-right text-[11px] leading-none text-gray-400">
                <span>{{ $byCost ? $usd($dailyMax) : $tokens((int) $dailyMax) }}</span>
                <span>0</span>
            </div>
            <div class="min-w-0 flex-1">
                <div class="relative flex h-44 items-end gap-[2px] border-b border-gray-200 sm:gap-1 dark:border-white/10" x-on:mouseleave="tip = ''">
                    <div class="pointer-events-none absolute inset-x-0 top-0 border-t border-dashed border-gray-200 dark:border-white/10"></div>
                    <div class="pointer-events-none absolute inset-x-0 top-1/2 border-t border-dashed border-gray-100 dark:border-white/5"></div>
                    @foreach ($stats['daily'] as $day)
                        @php($dayTip = $day['label'].': '.$usd($day['cost']).' · запитів '.$day['requests'].' · токенів '.$tokens($day['tokens']))
                        <button type="button" class="group relative flex h-full min-w-0 flex-1 items-end focus:outline-none" title="{{ $dayTip }}"
                                x-on:mouseenter="tip = @js($dayTip)" x-on:focus="tip = @js($dayTip)" x-on:click="tip = @js($dayTip)">
                            <span class="block w-full rounded-t-sm transition-opacity group-hover:opacity-75 group-focus:opacity-75"
                                  style="height: {{ $height($dailyValue($day), $dailyMax) }}%; background: linear-gradient(to top, var(--primary-600), var(--primary-400))"></span>
                        </button>
                    @endforeach
                </div>
                <div class="mt-1 flex justify-between text-[11px] text-gray-400">
                    <span>{{ $stats['daily'][0]['label'] }}</span>
                    <span>{{ $stats['daily'][14]['label'] }}</span>
                    <span>{{ $stats['daily'][29]['label'] }}</span>
                </div>
            </div>
        </div>
        <p class="mt-3 min-h-5 text-sm text-gray-700 dark:text-gray-200" x-text="tip || @js($dailyHint)">{{ $dailyHint }}</p>
    </div>
</x-filament::section>

<div class="grid min-w-0 grid-cols-1 gap-6 lg:grid-cols-2">
    <x-filament::section>
        <x-slot name="heading">Стан черги</x-slot>
        <x-slot name="description">Усі записи за весь час</x-slot>

        @if ($donutTotal > 0)
            <div class="flex flex-col items-center gap-6 sm:flex-row">
                <div class="relative size-40 shrink-0">
                    <svg viewBox="0 0 42 42" class="size-full -rotate-90" aria-hidden="true">
                        <circle cx="21" cy="21" r="15.915" fill="none" stroke-width="5" class="stroke-gray-100 dark:stroke-white/10"></circle>
                        @php($offset = 0)
                        @foreach ($donut as [$label, $count, $color])
                            @php($share = $count / $donutTotal * 100)
                            <circle cx="21" cy="21" r="15.915" fill="none" stroke-width="5" style="stroke: {{ $color }}"
                                    stroke-dasharray="{{ round($share, 3) }} {{ round(100 - $share, 3) }}" stroke-dashoffset="{{ round(-$offset, 3) }}"><title>{{ $label }}: {{ $count }}</title></circle>
                            @php($offset += $share)
                        @endforeach
                    </svg>
                    <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
                        @if ($successRate !== null)
                            <span class="text-2xl font-semibold text-gray-950 dark:text-white">{{ $successRate }}%</span>
                            <span class="text-[11px] text-gray-500 dark:text-gray-400">успішних</span>
                        @else
                            <span class="text-2xl font-semibold text-gray-950 dark:text-white">{{ $donutTotal }}</span>
                            <span class="text-[11px] text-gray-500 dark:text-gray-400">записів</span>
                        @endif
                    </div>
                </div>
                <ul class="grid w-full gap-2 text-sm">
                    @foreach ($donut as [$label, $count, $color])
                        <li class="flex items-center gap-2">
                            <span class="size-3 shrink-0 rounded-full" style="background: {{ $color }}"></span>
                            <span class="text-gray-700 dark:text-gray-200">{{ $label }}</span>
                            <span class="ml-auto font-medium text-gray-950 dark:text-white">{{ $tokens($count) }}</span>
                            <span class="w-10 text-right text-xs text-gray-400">{{ round($count / $donutTotal * 100) }}%</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">Перекладів ще не було.</p>
        @endif
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">{{ $monthlyByCost ? 'Витрати' : 'Запити' }} за типами матеріалів</x-slot>
        <x-slot name="description">{{ \Illuminate\Support\Str::ucfirst($stats['month_label']) }}</x-slot>

        @if (count($stats['by_type']))
            <ul class="grid gap-3 text-sm">
                @foreach (array_slice($stats['by_type'], 0, 8) as $row)
                    @php($value = $monthlyByCost ? $row['cost'] : $row['requests'])
                    <li class="min-w-0">
                        <div class="flex items-baseline gap-2">
                            <span class="min-w-0 truncate text-gray-700 dark:text-gray-200">{{ $row['label'] }}</span>
                            <span class="ml-auto shrink-0 font-medium text-gray-950 dark:text-white">{{ $monthlyByCost ? $usd($row['cost']) : $tokens($row['requests']) }}</span>
                            @if ($monthlyByCost)
                                <span class="shrink-0 text-xs text-gray-400">{{ $row['requests'] }} зап.</span>
                            @endif
                        </div>
                        <div class="mt-1 h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                            <div class="h-full rounded-full" style="width: {{ $height($value, $typeMax) }}%; background: linear-gradient(to right, var(--primary-400), var(--primary-600))"></div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">Цього місяця запитів ще не було.</p>
        @endif
    </x-filament::section>
</div>

<x-filament::section collapsible>
    <x-slot name="heading">По місяцях</x-slot>
    <x-slot name="description">{{ $monthlyByCost ? 'Приблизна вартість' : 'Кількість запитів' }} за останні 6 місяців</x-slot>

    <div class="flex h-40 items-end gap-2 sm:gap-4">
        @foreach ($stats['monthly'] as $item)
            @php($value = $monthlyByCost ? $item['cost'] : $item['requests'])
            <div class="flex h-full min-w-0 flex-1 flex-col items-center justify-end gap-1" title="{{ $item['label'] }}: {{ $usd($item['cost']) }}, запитів {{ $item['requests'] }}">
                <span class="truncate text-[11px] font-medium text-gray-700 dark:text-gray-200">{{ $value > 0 ? ($monthlyByCost ? $usd($item['cost']) : $item['requests']) : '' }}</span>
                <div class="w-full max-w-16 rounded-t-md" style="height: {{ $height($value, $monthlyByCost ? $monthlyMax : $monthlyRequestsMax) }}%; background: {{ $loop->last ? 'linear-gradient(to top, var(--primary-600), var(--primary-400))' : 'var(--gray-300)' }}"></div>
            </div>
        @endforeach
    </div>
    <div class="mt-1 flex gap-2 border-t border-gray-200 pt-1 sm:gap-4 dark:border-white/10">
        @foreach ($stats['monthly'] as $item)
            <span class="min-w-0 flex-1 truncate text-center text-[11px] text-gray-500 dark:text-gray-400">{{ $item['label'] }}</span>
        @endforeach
    </div>
</x-filament::section>

@if ($stats['recent']->isNotEmpty())
    <x-filament::section collapsible>
        <x-slot name="heading">Останні переклади</x-slot>

        {{-- Телефон: картки замість таблиці, щоб довгі назви не розтягували сторінку. --}}
        <ul class="divide-y divide-gray-100 md:hidden dark:divide-white/5">
            @foreach ($stats['recent'] as $job)
                @php([$label, $color] = $statusLabels[$job->status] ?? [$job->status, ''])
                <li class="py-3 first:pt-0 last:pb-0">
                    <div class="break-words text-sm text-gray-950 dark:text-white">{{ $job->label }}</div>
                    <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                        <span class="font-medium {{ $color }}">{{ $label }}</span>
                        @if ($job->input_tokens)
                            <span>{{ $tokens($job->input_tokens) }} / {{ $tokens($job->output_tokens) }} ток.</span>
                            <span>{{ $usd($job->cost_usd) }}</span>
                        @endif
                        <span class="ml-auto">{{ $job->updated_at?->timezone('Europe/Kyiv')->format('d.m H:i') }}</span>
                    </div>
                    @if ($job->error)
                        <div class="mt-1 break-words text-xs text-danger-600 dark:text-danger-400">{{ $job->error }}</div>
                    @endif
                </li>
            @endforeach
        </ul>

        <div class="hidden overflow-x-auto md:block">
            <table class="w-full table-fixed text-left text-sm">
                <thead class="text-xs uppercase text-gray-500 dark:text-gray-400">
                    <tr>
                        <th class="py-2 pr-4 font-medium">Матеріал</th>
                        <th class="w-32 py-2 pr-4 font-medium">Стан</th>
                        <th class="w-36 py-2 pr-4 text-right font-medium">Токени</th>
                        <th class="w-24 py-2 pr-4 text-right font-medium">Вартість</th>
                        <th class="w-24 py-2 font-medium">Час</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($stats['recent'] as $job)
                        @php([$label, $color] = $statusLabels[$job->status] ?? [$job->status, ''])
                        <tr class="align-top">
                            <td class="py-2 pr-4">
                                <div class="truncate text-gray-950 dark:text-white" title="{{ $job->label }}">{{ $job->label }}</div>
                                @if ($job->error)
                                    <div class="mt-0.5 break-words text-xs text-gray-500 dark:text-gray-400">{{ $job->error }}</div>
                                @endif
                            </td>
                            <td class="py-2 pr-4 whitespace-nowrap {{ $color }}">{{ $label }}</td>
                            <td class="py-2 pr-4 text-right whitespace-nowrap">
                                @if ($job->input_tokens)
                                    {{ $tokens($job->input_tokens) }} / {{ $tokens($job->output_tokens) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="py-2 pr-4 text-right whitespace-nowrap">{{ $job->input_tokens ? $usd($job->cost_usd) : '—' }}</td>
                            <td class="py-2 whitespace-nowrap text-gray-500 dark:text-gray-400">{{ $job->updated_at?->timezone('Europe/Kyiv')->format('d.m H:i') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>
@endif
</div>
