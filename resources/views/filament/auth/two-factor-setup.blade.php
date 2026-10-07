<x-filament-panels::page.simple>
    @if ($this->recoveryCodes)
        <div class="space-y-4">
            <div class="rounded-lg border border-warning-300 bg-warning-50 p-4 text-sm text-warning-900">
                <p class="font-semibold">Збережіть коди відновлення зараз — вони показуються лише один раз.</p>
                <p class="mt-1">Кожен код діє один раз і замінює застосунок, якщо телефон втрачено. Роздрукуйте або збережіть у менеджері паролів; не зберігайте поруч із паролем у відкритому вигляді.</p>
            </div>

            <ul class="grid grid-cols-2 gap-2 rounded-lg bg-gray-100 p-4 font-mono text-sm dark:bg-gray-800" data-recovery-codes>
                @foreach ($this->recoveryCodes as $code)
                    <li>{{ $code }}</li>
                @endforeach
            </ul>

            <div class="flex flex-col gap-2 sm:flex-row">
                <x-filament::button color="gray" x-on:click="navigator.clipboard?.writeText($el.closest('div').parentElement.querySelector('[data-recovery-codes]').innerText)">
                    Скопіювати коди
                </x-filament::button>
                <x-filament::button wire:click="finish">
                    Я зберіг коди — перейти до адмінки
                </x-filament::button>
            </div>
        </div>
    @else
        @if ($this->pendingSecret)
            <div class="space-y-3 text-sm">
                <ol class="list-decimal space-y-1 pl-5">
                    <li>Встановіть Google Authenticator, Aegis або інший TOTP-застосунок.</li>
                    <li>Відскануйте QR-код (або введіть ключ вручну).</li>
                    <li>Введіть шестизначний код із застосунку та натисніть «Підтвердити».</li>
                </ol>

                <div class="flex justify-center rounded-lg bg-white p-3">
                    {!! $this->qrCode() !!}
                </div>

                <p class="break-all text-center font-mono text-xs text-gray-500" data-secret>
                    Ключ вручну: {{ chunk_split($this->pendingSecret, 4, ' ') }}
                </p>
            </div>
        @else
            <p class="text-sm text-gray-600 dark:text-gray-300">
                Застосунок підключено {{ $this->user()->two_factor_confirmed_at?->timezone('Europe/Kyiv')->format('d.m.Y H:i') }}.
                Лишилося кодів відновлення: <strong>{{ count($this->user()->two_factor_recovery_codes ?? []) }}</strong>.
                Для нових кодів або перепідключення введіть поточний код із застосунку.
            </p>
        @endif

        <x-filament-panels::form id="form" wire:submit="{{ $this->pendingSecret ? 'confirm' : 'regenerateRecoveryCodes' }}">
            {{ $this->form }}

            <x-filament-panels::form.actions
                :actions="$this->getCachedFormActions()"
                :full-width="$this->hasFullWidthFormActions()"
            />
        </x-filament-panels::form>

        @unless ($this->pendingSecret)
            <div class="mt-4 text-center text-sm">
                <a href="{{ filament()->getUrl() }}" class="text-primary-600 underline">Повернутися до адмінки</a>
            </div>
        @endunless
    @endif
</x-filament-panels::page.simple>
