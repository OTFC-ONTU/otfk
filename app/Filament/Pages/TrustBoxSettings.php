<?php

namespace App\Filament\Pages;

use App\Filament\Support\SettingsFormPage;
use App\Mail\TrustBoxMessage;
use App\Models\TrustBoxStat;
use App\Services\TrustBox\TrustBox;
use App\Services\TrustBox\TrustBoxMailer;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Log;

/**
 * «Скринька довіри» (App\Services\TrustBox\TrustBox): службова скринька Gmail
 * і пароль застосунку, список отримувачів (1–5, кожен отримує окремий лист),
 * ключі Cloudflare Turnstile, тестовий лист і лічильники за місяць.
 * Пароль і секретний ключ зберігаються зашифрованими й у форму не повертаються.
 * Увімкнути скриньку можна лише після успішного тестового листа з цими налаштуваннями.
 */
class TrustBoxSettings extends SettingsFormPage
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?int $navigationSort = 8;

    protected static ?string $navigationLabel = 'Скринька довіри';

    protected static ?string $title = 'Скринька довіри';

    protected static ?string $slug = 'settings-trust-box';

    protected static string $settingsGroup = 'trust_box';

    protected static function keys(): array
    {
        return [
            TrustBox::ENABLED => 'text',
            TrustBox::SENDER => 'text',
            TrustBox::RECIPIENTS => 'text',
            TrustBox::TURNSTILE_SITE_KEY => 'text',
        ];
    }

    protected function fromSettings(array $state): array
    {
        $state[TrustBox::ENABLED] = ($state[TrustBox::ENABLED] ?? '') === '1';
        $state[TrustBox::RECIPIENTS] = TrustBox::recipients((string) ($state[TrustBox::RECIPIENTS] ?? '')) ?: [['email' => '', 'label' => 'Директор']];
        $state['app_password'] = null;
        $state['turnstile_secret'] = null;

        return $state;
    }

    protected function toSettings(array $state): array
    {
        $state[TrustBox::ENABLED] = ($state[TrustBox::ENABLED] ?? false) ? '1' : '0';
        $state[TrustBox::SENDER] = mb_strtolower(trim((string) ($state[TrustBox::SENDER] ?? '')));
        $state[TrustBox::RECIPIENTS] = json_encode(
            TrustBox::normalizeRecipients(array_values((array) ($state[TrustBox::RECIPIENTS] ?? []))),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
        $state[TrustBox::TURNSTILE_SITE_KEY] = trim((string) ($state[TrustBox::TURNSTILE_SITE_KEY] ?? ''));

        return $state;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Стан')
                    ->schema([
                        Text::make(fn () => $this->statusText()),
                        Text::make(function (): string {
                            $s = TrustBoxStat::forMonth();

                            return 'За поточний місяць: надіслано — '.$s['sent'].', відхилено як спам — '.$s['rejected']
                                .', помилок надсилання — '.$s['failed'].', Turnstile був недоступний — '.$s['captcha_unavailable'].'.';
                        }),
                        Toggle::make(TrustBox::ENABLED)->label('Скринька довіри працює на сайті')
                            ->helperText('Коли вимкнено, посилання зникають із шапки, меню й підвалу, а сторінка /skrynka-doviry показує контакти приймальні замість форми. Увімкнути можна лише після успішного тестового листа.'),
                    ]),
                Section::make('Пошта-відправник (Gmail)')
                    ->description('Окрема скринька лише для цих листів. Пароль застосунку створюється в акаунті Google: Безпека → Двоетапна перевірка → Паролі застосунків.')
                    ->columns(2)
                    ->schema([
                        TextInput::make(TrustBox::SENDER)->label('Адреса відправника')
                            ->email()->required()->maxLength(190)
                            ->rule('ends_with:@gmail.com')
                            ->validationMessages(['ends_with' => 'Потрібна адреса @gmail.com — пароль застосунку працює лише з Gmail.'])
                            ->helperText('Від цієї адреси отримувачі бачитимуть листи.'),
                        TextInput::make('app_password')->label('Пароль застосунку')
                            ->password()->revealable()->autocomplete('new-password')->maxLength(40)
                            ->rule(fn () => function (string $attribute, $value, \Closure $fail): void {
                                if (filled($value) && ! TrustBox::isValidPassword((string) $value)) {
                                    $fail('Пароль застосунку Gmail — 16 латинських літер (пробіли не важливі).');
                                }
                            })
                            ->helperText(fn () => match (true) {
                                TrustBox::hasUnreadablePassword() => 'Збережений пароль не вдається розшифрувати (змінився APP_KEY) — введіть його знову.',
                                TrustBox::hasPassword() => 'Пароль збережено. Залиште поле порожнім, щоб не змінювати.',
                                default => 'Пароль ще не збережено. 16 літер, пробіли не важливі.',
                            }),
                    ]),
                Section::make('Отримувачі')
                    ->description('Від 1 до '.TrustBox::MAX_RECIPIENTS.' адрес. Кожен отримує окремий лист і не бачить інших. На сайті завжди написано, що звернення отримує директор; адреси ніде не показуються.')
                    ->schema([
                        Repeater::make(TrustBox::RECIPIENTS)->hiddenLabel()
                            ->schema([
                                TextInput::make('email')->label('Адреса')->email()->required()->maxLength(190)->distinct(),
                                TextInput::make('label')->label('Для кого (лише в адмінці)')->maxLength(100)
                                    ->placeholder('Наприклад: Директор'),
                            ])
                            ->columns(2)
                            ->minItems(1)->maxItems(TrustBox::MAX_RECIPIENTS)
                            ->defaultItems(1)
                            ->reorderable(false)
                            ->addActionLabel('Додати отримувача'),
                    ]),
                Section::make('Захист від ботів (Cloudflare Turnstile)')
                    ->description('Пастка для ботів, мітка часу, ліміти й фільтр тексту працюють завжди. Turnstile — додатково, коли задано обидва ключі (dash.cloudflare.com → Turnstile → Add widget; домени otfk.od.ua, new.otfk.od.ua, just-test.shop). Якщо Cloudflare не відповідає, перевірка пропускається.')
                    ->columns(2)
                    ->schema([
                        TextInput::make(TrustBox::TURNSTILE_SITE_KEY)->label('Site key')->maxLength(100)
                            ->regex('/^[0-9A-Za-z_-]+$/'),
                        TextInput::make('turnstile_secret')->label('Secret key')
                            ->password()->revealable()->autocomplete('off')->maxLength(200)->regex('/^\S+$/')
                            ->helperText(fn () => TrustBox::turnstileSecret() !== null
                                ? 'Ключ збережено. Залиште поле порожнім, щоб не змінювати.'
                                : 'Ключ ще не збережено.'),
                    ]),
            ])
            ->statePath('data');
    }

    private function statusText(): string
    {
        if (! TrustBox::configured()) {
            return 'Скринька не налаштована: потрібні адреса відправника, пароль застосунку й хоча б один отримувач.';
        }
        if (! TrustBox::verified()) {
            return 'Налаштування ще не перевірені: надішліть тестовий лист, після цього скриньку можна ввімкнути.';
        }
        $at = TrustBox::verifiedAt()?->timezone('Europe/Kyiv')->format('d.m.Y H:i');

        return 'Тестовий лист з цими налаштуваннями надіслано успішно'.($at ? ' '.$at : '').'. '
            .(TrustBox::enabled() ? 'Скринька працює на сайті.' : 'Скриньку можна вмикати.');
    }

    /** @return array<Action> */
    public function getFormActions(): array
    {
        return [
            ...parent::getFormActions(),
            Action::make('sendTest')->label('Надіслати тестовий лист')->color('gray')->action('sendTest'),
        ];
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('forgetPassword')->label('Видалити пароль')->color('danger')->icon('heroicon-o-trash')
                ->visible(fn () => filled(\App\Models\Setting::get(TrustBox::PASSWORD)))
                ->requiresConfirmation()
                ->modalDescription('Скринька перестане надсилати листи й зникне з сайту, доки не буде введено новий пароль і надіслано тестовий лист.')
                ->action(function (): void {
                    abort_unless(static::canAccess(), 403);
                    TrustBox::storeSecret(TrustBox::PASSWORD, null);
                    TrustBox::storeValue(TrustBox::ENABLED, '0');
                    $this->data[TrustBox::ENABLED] = false;
                    Notification::make()->title('Пароль видалено, скриньку вимкнено')->success()->send();
                }),
        ];
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $state = $this->form->getState();
        if (! $this->recipientsAreValid($state)) {
            return;
        }

        $password = TrustBox::normalizePassword($state['app_password'] ?? '');
        $secret = trim((string) ($state['turnstile_secret'] ?? ''));

        parent::save();

        if ($password !== '') {
            TrustBox::storeSecret(TrustBox::PASSWORD, $password);
        }
        if ($secret !== '') {
            TrustBox::storeSecret(TrustBox::TURNSTILE_SECRET, $secret);
        }
        $this->data['app_password'] = null;
        $this->data['turnstile_secret'] = null;

        if (TrustBox::enabled() && ! TrustBox::verified()) {
            TrustBox::storeValue(TrustBox::ENABLED, '0');
            $this->data[TrustBox::ENABLED] = false;
            Notification::make()->title('Скриньку не ввімкнено')
                ->body('Налаштування пошти змінилися або ще не перевірені. Надішліть тестовий лист і ввімкніть скриньку знову.')
                ->warning()->send();
        }
    }

    /** Тестовий лист кожному отримувачу з поточних (навіть незбережених) значень форми. */
    public function sendTest(TrustBoxMailer $mailer): void
    {
        abort_unless(static::canAccess(), 403);

        $state = $this->form->getState();
        if (! $this->recipientsAreValid($state)) {
            return;
        }

        $sender = mb_strtolower(trim((string) ($state[TrustBox::SENDER] ?? '')));
        $password = TrustBox::normalizePassword($state['app_password'] ?? '') ?: TrustBox::password();
        $recipients = TrustBox::normalizeRecipients(array_values((array) ($state[TrustBox::RECIPIENTS] ?? [])));

        if ($password === null) {
            Notification::make()->title('Введіть пароль застосунку')->warning()->send();

            return;
        }

        @set_time_limit(120);
        $results = $mailer->send($sender, $password, array_column($recipients, 'email'), new TrustBoxMessage($sender, '', test: true));
        $failed = array_filter($results);

        if ($failed === []) {
            TrustBox::markVerified(TrustBox::configHash($sender, $password, $recipients));
            Notification::make()->title('Тестовий лист надіслано')
                ->body('Отримувачів: '.count($results).'. Перевірте пошту (і папку «Спам»). '
                    .($this->formMatchesSaved($sender, $password, $recipients) ? 'Тепер скриньку можна ввімкнути.' : 'Збережіть налаштування, щоб увімкнути скриньку.'))
                ->success()->persistent()->send();

            return;
        }

        foreach ($failed as $email => $error) {
            Log::channel('security')->warning('trust_box.test_failed', ['recipient' => $email, 'code' => TrustBoxMailer::code($error)]);
        }

        Notification::make()->title('Не вдалося надіслати тестовий лист')
            ->body(collect($results)->map(fn (?string $error, string $email) => $email.': '.($error ?? 'надіслано'))->implode("\n"))
            ->danger()->persistent()->send();
    }

    /** @param  list<array{email: string, label: string}>  $recipients */
    private function formMatchesSaved(string $sender, string $password, array $recipients): bool
    {
        return hash_equals(TrustBox::currentConfigHash(), TrustBox::configHash($sender, $password, $recipients));
    }

    private function recipientsAreValid(array $state): bool
    {
        $raw = array_values((array) ($state[TrustBox::RECIPIENTS] ?? []));
        $recipients = TrustBox::normalizeRecipients($raw);
        $sender = mb_strtolower(trim((string) ($state[TrustBox::SENDER] ?? '')));

        $error = match (true) {
            $recipients === [] => 'Додайте хоча б одного отримувача.',
            count($recipients) !== count($raw) => 'Адреси отримувачів повторюються.',
            in_array($sender, array_column($recipients, 'email'), true) => 'Адреса відправника не може бути серед отримувачів.',
            default => null,
        };

        if ($error !== null) {
            Notification::make()->title($error)->danger()->send();

            return false;
        }

        return true;
    }
}
