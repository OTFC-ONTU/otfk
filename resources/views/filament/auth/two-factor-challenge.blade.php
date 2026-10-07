<x-filament-panels::page.simple>
    <x-filament-panels::form id="form" wire:submit="verify">
        {{ $this->form }}

        <x-filament-panels::form.actions
            :actions="$this->getCachedFormActions()"
            :full-width="$this->hasFullWidthFormActions()"
        />
    </x-filament-panels::form>

    <form method="POST" action="{{ filament()->getLogoutUrl() }}" class="mt-4 text-center text-sm">
        @csrf
        <button type="submit" class="text-gray-500 underline hover:text-gray-700">Вийти та увійти іншим користувачем</button>
    </form>
</x-filament-panels::page.simple>
