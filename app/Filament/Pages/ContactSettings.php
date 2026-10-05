<?php

namespace App\Filament\Pages;

use App\Filament\Support\SettingsFormPage;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Form;

/**
 * Контакти та соцмережі одним екраном — замість пошуку ключів
 * contact_* / social_* у сирому списку налаштувань.
 */
class ContactSettings extends SettingsFormPage
{
    protected static ?string $navigationIcon = 'heroicon-o-phone';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Контакти та соцмережі';

    protected static ?string $title = 'Контакти та соцмережі';

    protected static ?string $slug = 'settings-contacts';

    protected static string $settingsGroup = 'contacts';

    protected static function keys(): array
    {
        return [
            'contact_address' => 'text',
            'contact_phone' => 'text',
            'contact_email' => 'text',
            'work_hours' => 'text',
            'feedback_email' => 'text',
            'map_embed' => 'url',
            'social_facebook' => 'url',
            'social_instagram' => 'url',
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Контактні дані')
                    ->description('Показуються у шапці, підвалі та на сторінці «Контакти».')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('contact_address')->label('Адреса'),
                        static::englishField('contact_address', 'Адреса'),
                        Forms\Components\TextInput::make('contact_phone')->label('Телефон')
                            ->helperText('Формат вільний, напр. (048) 753-16-51.'),
                        Forms\Components\TextInput::make('contact_email')->label('E-mail')->email(),
                        Forms\Components\TextInput::make('work_hours')->label('Години роботи')
                            ->helperText('Напр. «Пн–Пт 8:30–17:00». Порожнє — рядок приховано.'),
                        static::englishField('work_hours', 'Години роботи'),
                        Forms\Components\TextInput::make('feedback_email')->label('E-mail для звернень з сайту')->email()
                            ->helperText('Куди надсилати листи з форм «Контакти» та заявок абітурієнтів. Порожнє — на адресу з поля «E-mail» вище.'),
                    ]),
                Forms\Components\Section::make('Карта')
                    ->schema([
                        Forms\Components\TextInput::make('map_embed')->label('Посилання для вбудованої карти')->url()
                            ->helperText('Google Maps → «Поділитися» → «Вбудовування карти» → скопіюйте адресу з атрибута src (починається з https://www.google.com/maps/embed). Порожнє — карти немає.'),
                    ]),
                Forms\Components\Section::make('Соцмережі')
                    ->description('Посилання у шапці та підвалі сайту; порожнє — посилання приховано.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('social_facebook')->label('Facebook')->url(),
                        Forms\Components\TextInput::make('social_instagram')->label('Instagram')->url(),
                    ]),
            ])
            ->statePath('data');
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewSite')->label('Переглянути на сайті')->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')->url(route('contacts'))->openUrlInNewTab(),
        ];
    }
}
