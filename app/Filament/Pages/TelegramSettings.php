<?php

namespace App\Filament\Pages;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TextInput;
use App\Filament\Support\SettingsFormPage;
use App\Services\TelegramPoster;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;

/**
 * Автопостинг новин у Telegram. Токен — поле-пароль (маскується на екрані).
 * Кнопка тестового повідомлення бере токен і канал прямо з форми.
 */
class TelegramSettings extends SettingsFormPage
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-paper-airplane';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Telegram';

    protected static ?string $title = 'Telegram';

    protected static ?string $slug = 'settings-telegram';

    protected static function keys(): array
    {
        return [
            'telegram_autopost' => 'text',
            'telegram_bot_token' => 'text',
            'telegram_channel' => 'text',
        ];
    }

    protected function fromSettings(array $state): array
    {
        $state['telegram_autopost'] = ($state['telegram_autopost'] ?? '') === '1';

        return $state;
    }

    protected function toSettings(array $state): array
    {
        $state['telegram_autopost'] = ($state['telegram_autopost'] ?? false) ? '1' : '0';

        return $state;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Автопостинг новин')
                    ->description('Опублікована новина автоматично надсилається в Telegram-канал коледжу — одноразово, при першій появі на сайті.')
                    ->schema([
                        Toggle::make('telegram_autopost')->label('Надсилати нові новини в Telegram')
                            ->helperText('Працює лише коли заповнені токен бота і канал.'),
                        TextInput::make('telegram_bot_token')->label('Токен бота')->password()->revealable()
                            ->helperText('Токен від @BotFather (вигляд: 1234567890:AA…). Бот має бути адміністратором каналу.'),
                        TextInput::make('telegram_channel')->label('Канал')
                            ->helperText('@назва_каналу або числовий ID (-100…).'),
                    ]),
            ])
            ->statePath('data');
    }

    /** @return array<Action> */
    public function getFormActions(): array
    {
        return [
            ...parent::getFormActions(),
            Action::make('sendTest')->label('Надіслати тестове повідомлення')->color('gray')->action('sendTest'),
        ];
    }

    /** Тестова відправка з поточних (навіть незбережених) значень форми. */
    public function sendTest(): void
    {
        abort_unless(static::canAccess(), 403);

        $state = $this->form->getState();
        $token = trim((string) ($state['telegram_bot_token'] ?? ''));
        $channel = trim((string) ($state['telegram_channel'] ?? ''));

        if ($token === '' || $channel === '') {
            Notification::make()->title('Заповніть токен бота і канал')->warning()->send();

            return;
        }

        $error = TelegramPoster::sendTest($token, $channel);

        $error === null
            ? Notification::make()->title('Тестове повідомлення надіслано')->body('Перевірте канал.')->success()->send()
            : Notification::make()->title('Не вдалося надіслати')->body($error)->danger()->send();
    }
}
