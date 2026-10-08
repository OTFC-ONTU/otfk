<x-filament-panels::page.simple>
    <form id="form" wire:submit="verify" class="fi-sc-form">
        {{ $this->form }}

        <x-filament::actions
            :actions="$this->getCachedFormActions()"
            :full-width="$this->hasFullWidthFormActions()"
        />
    </form>

    <form method="POST" action="{{ filament()->getLogoutUrl() }}" class="mt-4 text-center text-sm">
        @csrf
        <button type="submit" class="text-gray-500 underline hover:text-gray-700">Вийти та увійти іншим користувачем</button>
    </form>
</x-filament-panels::page.simple>
