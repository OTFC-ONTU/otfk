<?php

namespace App\Filament\Auth;

use App\Filament\Auth\Concerns\SimpleAuthLayout;
use App\Models\User;
use App\Support\TwoFactor;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use DanHarrin\LivewireRateLimiting\WithRateLimiting;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;

/**
 * Підключення застосунку-автентифікатора (обов'язкове для всіх користувачів
 * панелі). Секрет живе в компоненті до першого правильного коду — лише тоді
 * він записується в users і видаються коди відновлення, які показуються
 * один раз. Користувач з уже підключеним фактором, що пройшов код у цій сесії,
 * може перегенерувати коди відновлення або перепідключити застосунок
 * (обидва — після введення поточного коду).
 *
 * @property Form $form
 */
class TwoFactorSetup extends Page
{
    use InteractsWithFormActions;
    use SimpleAuthLayout;
    use WithRateLimiting;

    protected static string $view = 'filament.auth.two-factor-setup';

    /** Проста (без навігації) розмітка, як у сторінки входу. */
    protected static string $layout = 'filament-panels::components.layout.simple';

    protected static ?string $slug = 'two-factor-setup';

    protected static bool $shouldRegisterNavigation = false;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** Непідтверджений секрет (у стані компонента, не в БД). */
    public ?string $pendingSecret = null;

    /** Коди відновлення для одноразового показу після підтвердження. */
    public ?array $recoveryCodes = null;

    public function mount(): void
    {
        $user = Filament::auth()->user();
        if (! $user instanceof User) {
            redirect()->to(Filament::getLoginUrl());

            return;
        }

        // Підключений фактор без пройденого коду в цій сесії — спочатку код.
        if ($user->hasTwoFactor() && ! app(TwoFactor::class)->passed($user)) {
            redirect()->to(TwoFactorChallenge::getUrl());

            return;
        }

        if (! $user->hasTwoFactor()) {
            $this->pendingSecret = app(TwoFactor::class)->generateSecret();
        }

        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('code')
                    ->label('Код із застосунку')
                    ->required()
                    ->autocomplete('one-time-code')
                    ->rule('digits:6')
                    ->helperText('Шість цифр, які показує застосунок після сканування QR-коду.'),
            ])
            ->statePath('data');
    }

    public function user(): User
    {
        /** @var User $user */
        $user = Filament::auth()->user();

        return $user;
    }

    public function qrCode(): ?string
    {
        return $this->pendingSecret ? app(TwoFactor::class)->qrCodeSvg($this->user(), $this->pendingSecret) : null;
    }

    /** Перший крок: увімкнути фактор після правильного коду. */
    public function confirm(): void
    {
        $this->throttle();

        if ($this->pendingSecret === null) {
            throw ValidationException::withMessages(['data.code' => 'Застосунок уже підключено.']);
        }

        $code = (string) ($this->form->getState()['code'] ?? '');
        $codes = app(TwoFactor::class)->confirm($this->user(), $this->pendingSecret, $code);

        if ($codes === null) {
            throw ValidationException::withMessages(['data.code' => 'Код не підійшов. Переконайтеся, що QR-код відскановано, а час на телефоні точний.']);
        }

        $this->pendingSecret = null;
        $this->recoveryCodes = $codes;
        $this->form->fill();
    }

    /** Перегенерувати коди відновлення (потрібен поточний код). */
    public function regenerateRecoveryCodes(): void
    {
        $this->throttle();
        $user = $this->user();
        $code = (string) ($this->form->getState()['code'] ?? '');

        $twoFactor = app(TwoFactor::class);
        $step = $user->hasTwoFactor() ? $twoFactor->verifyCode((string) $user->two_factor_secret, $code, $user->two_factor_last_used) : null;
        if ($step === null) {
            throw ValidationException::withMessages(['data.code' => 'Невірний код.']);
        }

        $codes = $twoFactor->generateRecoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => $codes, 'two_factor_last_used' => $step])->saveQuietly();
        $this->recoveryCodes = $codes;
        $this->form->fill();

        Notification::make()->title('Нові коди відновлення створено')->body('Старі коди більше не діють.')->success()->send();
    }

    /** Перепідключити застосунок (потрібен поточний код): новий секрет до підтвердження не активний. */
    public function startReenroll(): void
    {
        $this->throttle();
        $user = $this->user();
        $code = (string) ($this->form->getState()['code'] ?? '');

        $twoFactor = app(TwoFactor::class);
        $step = $user->hasTwoFactor() ? $twoFactor->verifyCode((string) $user->two_factor_secret, $code, $user->two_factor_last_used) : null;
        if ($step === null) {
            throw ValidationException::withMessages(['data.code' => 'Невірний код.']);
        }

        $user->forceFill(['two_factor_last_used' => $step])->saveQuietly(); // той самий код удруге не приймається
        $this->pendingSecret = $twoFactor->generateSecret();
        $this->recoveryCodes = null;
        $this->form->fill();
    }

    public function finish(): void
    {
        redirect()->intended(Filament::getUrl());
    }

    private function throttle(): void
    {
        try {
            $this->rateLimit(10);
        } catch (TooManyRequestsException $exception) {
            throw ValidationException::withMessages(['data.code' => "Забагато спроб, зачекайте {$exception->secondsUntilAvailable} с."]);
        }
    }

    /** @return array<Action> */
    protected function getFormActions(): array
    {
        if ($this->pendingSecret !== null) {
            return [Action::make('confirm')->label('Підтвердити й увімкнути')->submit('confirm')];
        }

        return [
            Action::make('regenerateRecoveryCodes')->label('Нові коди відновлення')->submit('regenerateRecoveryCodes'),
            Action::make('startReenroll')->label('Перепідключити застосунок')->color('gray')->action('startReenroll'),
        ];
    }

    public function getTitle(): string|Htmlable
    {
        return 'Двофакторний захист';
    }

    public function getHeading(): string|Htmlable
    {
        return $this->pendingSecret !== null ? 'Підключіть застосунок-автентифікатор' : 'Двофакторний захист увімкнено';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return $this->pendingSecret !== null
            ? 'Вхід в адмінку вимагає коду з телефону. Це обов\'язково для всіх користувачів.'
            : null;
    }
}
