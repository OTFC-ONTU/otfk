<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\SettingResource\Pages\ListSettings;
use App\Filament\Resources\SettingResource\Pages\CreateSetting;
use App\Filament\Resources\SettingResource\Pages\EditSetting;
use App\Filament\Forms\EnglishTranslation;
use App\Filament\Resources\SettingResource\Pages;
use App\Models\Setting;
use App\Rules\SafeUrl;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SettingResource extends Resource
{
    protected static ?string $model = Setting::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string | \UnitEnum | null $navigationGroup = 'Налаштування';

    protected static ?string $navigationLabel = 'Розширені налаштування';

    protected static ?int $navigationSort = 8;

    protected static ?string $modelLabel = 'налаштування';

    protected static ?string $pluralModelLabel = 'Розширені налаштування';

    /** Сирий доступ до settings (включно з токеном Telegram) — лише адміністратору; див. також SettingPolicy. */
    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('key')->label('Ключ')->required()->maxLength(255)
                ->disabledOn('edit')->live()->helperText('Технічний ідентифікатор, напр. contact_phone.'),
            Select::make('type')->label('Тип значення')->default('text')->live()
                ->options([
                    'text' => 'Текст',
                    'textarea' => 'Багаторядковий',
                    'number' => 'Число',
                    'url' => 'Посилання',
                    'html' => 'HTML',
                    'image' => 'Зображення',
                ]),
            FileUpload::make('image_value')->label('Зображення')->image()->imageEditor()
                ->directory('settings')->columnSpanFull()
                ->visible(fn (Get $get) => $get('type') === 'image')
                ->helperText('Напр. логотип сайту. Рекомендований формат - PNG з прозорим тлом.'),
            Textarea::make('value')->label('Значення')->rows(3)->columnSpanFull()
                ->visible(fn (Get $get) => $get('type') !== 'image')
                ->rule(fn (Get $get) => $get('type') === 'url' ? new SafeUrl : null)
                ->helperText(fn (Get $get) => match ($get('key')) {
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
            TextInput::make('group')->label('Група')->default('general')->maxLength(255),
            EnglishTranslation::section(contentFields: [], primaryField: 'value', primaryLabel: 'Англійське значення', primaryRows: 4)
                ->visible(fn (Get $get) => Setting::supportsTranslation($get('key'), $get('type')))
                ->description('Перекладаються лише публічні текстові налаштування. Порожній оригінал приховує оголошення/позначку незалежно від перекладу. Стандартні підписи бренду й опис сайту без перекладу використовують англійський словник.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->description('Сирий key-value доступ на аварійний випадок. Звичайні налаштування зручніше міняти на сторінках «Контакти та соцмережі», «Оголошення», «Telegram», «Підвал і вигляд».')
            ->columns([
                TextColumn::make('translation_status')->label('Переклад EN')
                    ->state(fn (Setting $record) => Setting::supportsTranslation($record->key, $record->type) ? $record->translationStatus() : 'Не перекладається')->badge(),
                TextColumn::make('key')->label('Ключ')->searchable()->weight('bold'),
                TextColumn::make('value')->label('Значення')->limit(60)->color('gray'),
                TextColumn::make('group')->label('Група')->badge()->sortable(),
            ])
            ->defaultSort('group')
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSettings::route('/'),
            'create' => CreateSetting::route('/create'),
            'edit' => EditSetting::route('/{record}/edit'),
        ];
    }
}
