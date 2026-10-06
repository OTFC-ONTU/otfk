<x-filament-panels::page>
    @php
        $items = collect($this->report());
        $groups = $items->groupBy(fn ($i) => $i['source'] . ' — ' . $i['title']);
    @endphp

    @if ($items->isEmpty())
        <x-filament::section>
            <p class="text-sm text-success-600 dark:text-success-400">✓ Битих внутрішніх посилань не знайдено.</p>
        </x-filament::section>
    @else
        <div class="text-sm text-gray-500 dark:text-gray-400">
            Знайдено проблемних посилань:
            <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $items->count() }}</span>
            (матеріалів: {{ $groups->count() }}). Відкрийте матеріал і виправте або приберіть посилання.
        </div>

        @foreach ($groups as $heading => $links)
            <x-filament::section>
                <x-slot name="heading">{{ $heading }}</x-slot>
                <x-slot name="headerEnd">
                    <a href="{{ $links->first()['edit_url'] }}"
                       class="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400">Редагувати →</a>
                </x-slot>

                <ul class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($links as $link)
                        <li class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 py-2">
                            <code class="min-w-0 break-all text-xs text-gray-700 dark:text-gray-300">{{ $link['url'] }}</code>
                            <span class="shrink-0 text-xs font-medium text-danger-600 dark:text-danger-400">{{ $link['reason'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </x-filament::section>
        @endforeach
    @endif
</x-filament-panels::page>
