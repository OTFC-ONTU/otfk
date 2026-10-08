<?php

namespace App\Filament\Auth;

use Filament\Schemas\Schema;
use App\Filament\Auth\Concerns\SimpleAuthLayout;
use App\Models\User;
use App\Support\TwoFactor;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use DanHarrin\LivewireRateLimiting\WithRateLimiting;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Другий крок входу: шестизначний код із застосунку або код відновлення.
 * Ліміт 5 спроб/хв на IP; успіх позначає сесію (TwoFactor::pass) і веде
 * на панель, невдачі й блокування пишуться в канал `security`.
 *
 * @property \Filament\Schemas\Schema $form
 */
class TwoFactorChallenge extends Page
{
    use InteractsWithFormActions;
    use SimpleAuthLayout;
    use WithRateLimiting;

    protected string $view = 'filament.auth.two-factor-challenge';

    /** Проста (без навігації) розмітка, як у сторінки входу. */
    protected static string $layout = 'filament-panels::components.layout.simple';

    protected static ?string $slug = 'two-factor-challenge';

    protected static bool $shouldRegisterNavigation = false;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $user = Filament::auth()->user();

        if (! $user instanceof User) {
            redirect()->to(Filament::getLoginUrl());

            return;
        }

        if (! $user->hasTwoFactor()) {
            redirect()->to(TwoFactorSetup::getUrl());

            return;
        }

        if (app(TwoFactor::class)->passed($user)) {
            redirect()->intended(Filament::getUrl());

            return;
        }

        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('Код із застосунку або код відновлення')
                    ->required()
                    ->autofocus()
                    ->autocomplete('one-time-code')
                    ->maxLength(20)
                    ->helperText('Шість цифр із Google Authenticator / Aegis. Застосунок недоступний — введіть один із кодів відновлення (XXXXX-XXXXX); кожен діє один раз.'),
            ])
            ->statePath('data');
    }

    public function verify(): void
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            Log::channel('security')->warning('2fa.lockout', [
                'ip' => request()->ip(),
                'email' => Filament::auth()->user()?->email,
                'seconds' => $exception->secondsUntilAvailable,
            ]);

            Notification::make()
                ->title('Забагато спроб')
                ->body("Спробуйте ще раз через {$exception->secondsUntilAvailable} с.")
                ->danger()
                ->send();

            return;
        }

        $user = Filament::auth()->user();
        $code = (string) ($this->form->getState()['code'] ?? '');

        if (! $user instanceof User || ! app(TwoFactor::class)->challenge($user, $code)) {
            throw ValidationException::withMessages(['data.code' => 'Невірний код. Перевірте час на телефоні або скористайтеся кодом відновлення.']);
        }

        redirect()->intended(Filament::getUrl());
    }

    /** @return array<Action> */
    protected function getFormActions(): array
    {
        return [
            Action::make('verify')->label('Підтвердити')->submit('verify'),
        ];
    }

    public function logoutAction(): Action
    {
        return Action::make('logout')
            ->link()
            ->label('Вийти')
            ->url(Filament::getLogoutUrl(), shouldOpenInNewTab: false)
            ->extraAttributes(['x-on:click.prevent' => '$el.closest("form")?.submit()']);
    }

    public function getTitle(): string|Htmlable
    {
        return 'Підтвердження входу';
    }

    public function getHeading(): string|Htmlable
    {
        return 'Другий крок входу';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Введіть код із застосунку-автентифікатора.';
    }
}
