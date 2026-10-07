<?php

namespace App\Filament\Resources;

use App\Filament\Forms\EnglishTranslation;
use App\Filament\Resources\SettingResource\Pages;
use App\Models\Setting;
use App\Rules\SafeUrl;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SettingResource extends Resource
{
    protected static ?string $model = Setting::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Налаштування';

    protected static ?string $navigationLabel = 'Розширені налаштування';

    protected static ?int $navigationSort = 8;

    protected static ?string $modelLabel = 'налаштування';

    protected static ?string $pluralModelLabel = 'Розширені налаштування';

    /** Сирий доступ до settings (включно з токеном Telegram) — лише адміністратору; див. також SettingPolicy. */
    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('key')->label('Ключ')->required()->maxLength(255)
                ->disabledOn('edit')->live()->helperText('Технічний ідентифікатор, напр. contact_phone.'),
            Forms\Components\Select::make('type')->label('Тип значення')->default('text')->live()
                ->options([
                    'text' => 'Текст',
                    'textarea' => 'Багаторядковий',
                    'number' => 'Число',
                    'url' => 'Посилання',
                    'html' => 'HTML',
                    'image' => 'Зображення',
                ]),
            Forms\Components\FileUpload::make('image_value')->label('Зображення')->image()->imageEditor()
                ->directory('settings')->columnSpanFull()
                ->visible(fn (Forms\Get $get) => $get('type') === 'image')
                ->helperText('Напр. логотип сайту. Рекомендований формат - PNG з прозорим тлом.'),
            Forms\Components\Textarea::make('value')->label('Значення')->rows(3)->columnSpanFull()
                ->visible(fn (Forms\Get $get) => $get('type') !== 'image')
                ->rule(fn (Forms\Get $get) => $get('type') === 'url' ? new SafeUrl : null)
                ->helperText(fn (Forms\Get $get) => match ($get('key')) {
                    'site_version_label' => 'Напис у підвалі сайту (напр., «Бета-версія»). Порожнє значення — приховати позначку.',
                    'site_version_color' => 'Колір позначки версії: gold (золотий), green (зелений), blue (синій), red (червоний) або gray (сірий).',
                    'telegram_autopost' => 'Автопостинг новин у Telegram: 1 — увімкнено, 0 — вимкнено. Потрібні також telegram_bot_token і telegram_channel.',
                    'telegram_bot_token' => 'Токен бота від @BotFather (вигляд: 1234567890:AA…). Бот має бути адміністратором каналу.',
                    'telegram_channel' => 'Канал для постингу: @назва_каналу або числовий ID (-100…).',
                    'announcement_text' => 'Текст термінового оголошення у смузі над шапкою сайту. Порожнє — смуга прихована.',
                    'announcement_type' => 'Колір смуги: info (синій), warning (золотий) або danger (червоний).',
                    'announcement_url' => 'Необовʼязкове посилання, куди веде оголошення (напр., новина).',
                    'footer_about' => 'Текст «Про коледж» у підвалі сайту. Посилання-партнери підвалу редагуються у розділі «Швидкі посилання» (локація «Партнер у підвалі»).',
                    'social_youtube' => 'Посилання на YouTube-канал коледжу. Показується у блоці-заклику на сторінці «Відео»; порожнє — блок приховано.',
                    'banner_overlay_opacity' => 'Затемнення фото банера (0–100). Зручніше змінювати в розділі «Банери».',
                    'holiday_theme', 'holiday_theme_until' => 'Зручніше змінювати на сторінці «Налаштування → Святкова тема».',
                    'bells_second_shift' => 'Друга зміна в розкладі дзвінків: 1 — показувати, 0 — сховати. Зручніше перемикати кнопкою в розділі «Розклад дзвінків».',
                    default => null,
                }),
            Forms\Components\TextInput::make('group')->label('Група')->default('general')->maxLength(255),
            EnglishTranslation::section(contentFields: [], primaryField: 'value', primaryLabel: 'Англійське значення', primaryRows: 4)
                ->visible(fn (Forms\Get $get) => Setting::supportsTranslation($get('key'), $get('type')))
                ->description('Перекладаються лише публічні текстові налаштування. Порожній оригінал приховує оголошення/позначку незалежно від перекладу. Стандартні підписи бренду й опис сайту без перекладу використовують англійський словник.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->description('Сирий key-value доступ на аварійний випадок. Звичайні налаштування зручніше міняти на сторінках «Контакти та соцмережі», «Оголошення», «Telegram», «Підвал і вигляд».')
            ->columns([
                Tables\Columns\TextColumn::make('translation_status')->label('Переклад EN')
                    ->state(fn (Setting $record) => Setting::supportsTranslation($record->key, $record->type) ? $record->translationStatus() : 'Не перекладається')->badge(),
                Tables\Columns\TextColumn::make('key')->label('Ключ')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('value')->label('Значення')->limit(60)->color('gray'),
                Tables\Columns\TextColumn::make('group')->label('Група')->badge()->sortable(),
            ])
            ->defaultSort('group')
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSettings::route('/'),
            'create' => Pages\CreateSetting::route('/create'),
            'edit' => Pages\EditSetting::route('/{record}/edit'),
        ];
    }
}
