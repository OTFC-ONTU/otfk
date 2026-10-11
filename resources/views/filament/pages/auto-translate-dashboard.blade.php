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
@endphp

<div class="grid gap-6">
<x-filament::section>
    <x-slot name="heading">Використання API — {{ $stats['month_label'] }}</x-slot>
    <x-slot name="description">
        @if (! $stats['has_key'])
            Ключ API не збережено — автопереклад не працює.
        @elseif (! $stats['enabled'])
            Автопереклад вимкнено.
        @else
            Автопереклад увімкнено, модель {{ $stats['model'] }}@if ($stats['model_price']) ({{ $stats['model_price'] }})@endif.
        @endif
    </x-slot>

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
            <div class="text-sm text-gray-500 dark:text-gray-400">Приблизна вартість</div>
            <div class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $usd($stats['cost']) }}</div>
            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Минулий місяць: {{ $usd($stats['previous_cost']) }}
                @if ($stats['budget'])
                    · ліміт {{ $usd($stats['budget']) }}
                @endif
            </div>
        </div>
        <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
            <div class="text-sm text-gray-500 dark:text-gray-400">Вхідні токени</div>
            <div class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $tokens($stats['input']) }}</div>
            @if ($stats['cache'])
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">+ кеш: {{ $tokens($stats['cache']) }}</div>
            @endif
        </div>
        <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
            <div class="text-sm text-gray-500 dark:text-gray-400">Вихідні токени</div>
            <div class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $tokens($stats['output']) }}</div>
            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Разом із «міркуваннями» моделі</div>
        </div>
        <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
            <div class="text-sm text-gray-500 dark:text-gray-400">Запитів за місяць</div>
            <div class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $tokens($stats['requests']) }}</div>
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

@if ($stats['recent']->isNotEmpty())
    <x-filament::section collapsible>
        <x-slot name="heading">Останні переклади</x-slot>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="text-xs uppercase text-gray-500 dark:text-gray-400">
                    <tr>
                        <th class="py-2 pr-4 font-medium">Матеріал</th>
                        <th class="py-2 pr-4 font-medium">Стан</th>
                        <th class="py-2 pr-4 text-right font-medium">Токени</th>
                        <th class="py-2 pr-4 text-right font-medium">Вартість</th>
                        <th class="py-2 font-medium">Час</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($stats['recent'] as $job)
                        @php([$label, $color] = $statusLabels[$job->status] ?? [$job->status, ''])
                        <tr class="align-top">
                            <td class="max-w-md py-2 pr-4">
                                <div class="truncate text-gray-950 dark:text-white">{{ $job->label }}</div>
                                @if ($job->error)
                                    <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $job->error }}</div>
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
