<x-filament-widgets::widget>
    <x-filament::section
        heading="Чернетки"
        description="Неопубліковані сторінки, новини, спеціальності та підрозділи. «Перегляд» відкриє чернетку на сайті — її бачите лише ви.">
        <ul class="divide-y divide-gray-100 dark:divide-white/5">
            @foreach ($this->drafts() as $draft)
                <li class="flex items-center justify-between gap-4 py-2">
                    <span class="min-w-0 truncate">
                        <span class="text-xs text-gray-400">{{ $draft['type'] }}</span>
                        <span class="ml-1 font-medium text-gray-900 dark:text-gray-100">{{ $draft['title'] }}</span>
                        @if ($draft['updated'])
                            <span class="ml-2 text-xs text-gray-400">{{ $draft['updated']->format('d.m.Y') }}</span>
                        @endif
                    </span>
                    <span class="flex shrink-0 items-center gap-3 text-sm font-medium">
                        <a href="{{ $draft['edit_url'] }}"
                           class="text-primary-600 hover:underline dark:text-primary-400">Редагувати</a>
                        <a href="{{ $draft['view_url'] }}" target="_blank" rel="noopener"
                           class="text-gray-500 hover:underline dark:text-gray-400">Перегляд ↗</a>
                    </span>
                </li>
            @endforeach
        </ul>
    </x-filament::section>
</x-filament-widgets::widget>
