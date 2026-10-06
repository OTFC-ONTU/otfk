<x-filament-widgets::widget>
    <x-filament::section heading="Швидкі дії">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <a href="{{ \App\Filament\Resources\NewsResource::getUrl('create') }}"
               class="flex items-center gap-3 rounded-xl bg-primary-50 p-4 ring-1 ring-primary-200 transition hover:bg-primary-100 dark:bg-primary-500/10 dark:ring-primary-500/30 dark:hover:bg-primary-500/20">
                <x-filament::icon icon="heroicon-o-newspaper" class="h-6 w-6 text-primary-600 dark:text-primary-400" />
                <span class="text-sm font-semibold">Додати новину</span>
            </a>
            <a href="{{ \App\Filament\Resources\EventResource::getUrl('create') }}"
               class="flex items-center gap-3 rounded-xl bg-primary-50 p-4 ring-1 ring-primary-200 transition hover:bg-primary-100 dark:bg-primary-500/10 dark:ring-primary-500/30 dark:hover:bg-primary-500/20">
                <x-filament::icon icon="heroicon-o-calendar-days" class="h-6 w-6 text-primary-600 dark:text-primary-400" />
                <span class="text-sm font-semibold">Додати подію</span>
            </a>
            <a href="{{ \App\Filament\Resources\DocumentResource::getUrl('create') }}"
               class="flex items-center gap-3 rounded-xl bg-primary-50 p-4 ring-1 ring-primary-200 transition hover:bg-primary-100 dark:bg-primary-500/10 dark:ring-primary-500/30 dark:hover:bg-primary-500/20">
                <x-filament::icon icon="heroicon-o-document-arrow-up" class="h-6 w-6 text-primary-600 dark:text-primary-400" />
                <span class="text-sm font-semibold">Завантажити документ</span>
            </a>
            <a href="{{ \App\Filament\Resources\GalleryResource::getUrl('create') }}"
               class="flex items-center gap-3 rounded-xl bg-primary-50 p-4 ring-1 ring-primary-200 transition hover:bg-primary-100 dark:bg-primary-500/10 dark:ring-primary-500/30 dark:hover:bg-primary-500/20">
                <x-filament::icon icon="heroicon-o-photo" class="h-6 w-6 text-primary-600 dark:text-primary-400" />
                <span class="text-sm font-semibold">Додати галерею</span>
            </a>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
