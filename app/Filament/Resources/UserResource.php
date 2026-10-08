<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Support\TwoFactor;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Password;

/**
 * Облікові записи адмінки. Доступ — лише адміністраторам (UserPolicy +
 * canAccess); редактор не бачить розділ і отримує 403 на будь-яку дію.
 * Запобіжники «не видалити себе / останнього адміністратора» — у User::booted().
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-user-circle';

    protected static string | \UnitEnum | null $navigationGroup = 'Налаштування';

    protected static ?int $navigationSort = 9;

    protected static ?string $navigationLabel = 'Користувачі';

    protected static ?string $modelLabel = 'користувач';

    protected static ?string $pluralModelLabel = 'Користувачі';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Імʼя')->required()->maxLength(255),
            TextInput::make('email')->label('Електронна пошта')->email()->required()
                ->maxLength(255)->unique(ignoreRecord: true),

            Select::make('role')->label('Роль')->required()
                ->options(User::ROLES)->default(User::ROLE_EDITOR)->native(false)
                // Власну роль не змінюють: інакше адміністратор випадково позбавить себе доступу.
                ->disabled(fn (?User $record) => $record !== null && $record->id === auth()->id())
                ->dehydrated(fn (?User $record) => $record === null || $record->id !== auth()->id())
                ->helperText('Редактор працює лише з контентом. Адміністратор також керує користувачами, налаштуваннями та меню.'),

            TextInput::make('password')->label('Пароль')
                ->password()->revealable()->maxLength(255)
                ->required(fn (string $operation) => $operation === 'create')
                ->dehydrated(fn (?string $state) => filled($state))   // не зберігати, якщо порожнє
                ->rule(Password::default())
                ->confirmed()
                ->helperText('Щонайменше 12 символів, літери й цифри. Під час редагування залиште порожнім, щоб не змінювати пароль.'),
            TextInput::make('password_confirmation')->label('Підтвердження паролю')
                ->password()->revealable()->maxLength(255)
                ->dehydrated(false)
                ->required(fn (string $operation) => $operation === 'create'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Імʼя')->searchable()->weight('bold'),
                TextColumn::make('email')->label('Пошта')->searchable()->copyable()->color('gray'),
                TextColumn::make('role')->label('Роль')->badge()
                    ->formatStateUsing(fn (string $state) => User::ROLES[$state] ?? $state)
                    ->color(fn (string $state) => $state === User::ROLE_ADMIN ? 'danger' : 'gray'),
                IconColumn::make('two_factor_confirmed_at')->label('2FA')->boolean()
                    ->getStateUsing(fn (User $record) => $record->hasTwoFactor())
                    ->tooltip(fn (User $record) => $record->hasTwoFactor() ? 'Застосунок підключено' : 'Підключить при наступному вході'),
                TextColumn::make('last_login_at')->label('Останній вхід')
                    ->dateTime('d.m.Y H:i', 'Europe/Kyiv')->placeholder('ще не входив')->sortable(),
                TextColumn::make('created_at')->label('Створено')->dateTime('d.m.Y')->sortable(),
            ])
            ->defaultSort('id')
            ->recordActions([
                EditAction::make(),
                // Втрачений телефон і коди відновлення: адміністратор скидає фактор, користувач підключає застосунок заново при вході.
                Action::make('resetTwoFactor')
                    ->label('Скинути 2FA')
                    ->icon('heroicon-o-device-phone-mobile')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Скинути двофакторний захист?')
                    ->modalDescription('Користувач підключить застосунок заново при наступному вході. Робіть це лише після перевірки особи (телефон, зустріч), а не за листом.')
                    ->visible(fn (User $record) => $record->hasTwoFactor() && $record->id !== auth()->id())
                    ->action(function (User $record): void {
                        app(TwoFactor::class)->reset($record, 'admin:'.auth()->user()?->email);
                        Notification::make()->title('2FA скинуто')->body('Користувач підключить застосунок при наступному вході.')->success()->send();
                    }),
                DeleteAction::make()
                    ->visible(fn (User $record) => $record->id !== auth()->id()) // не дати видалити себе
                    ->before(fn (DeleteAction $action, User $record) => static::guardDeletion($action, $record)),
            ])
            // Масове видалення вимкнено: користувачів мало, а випадкове видалення себе чи останнього адміністратора неприпустиме.
            ->toolbarActions([]);
    }

    /**
     * Зрозуміле повідомлення замість помилки форми: останнього адміністратора
     * і себе видалити не можна (той самий запобіжник є в User::booted()).
     */
    public static function guardDeletion(Action $action, User $record): void
    {
        $reason = match (true) {
            $record->id === auth()->id() => 'Не можна видалити власний обліковий запис.',
            $record->isAdmin() && ! User::query()->whereKeyNot($record->id)->where('role', User::ROLE_ADMIN)->exists() => 'Це останній адміністратор: спочатку призначте іншого.',
            default => null,
        };

        if ($reason !== null) {
            Notification::make()->title($reason)->danger()->send();
            $action->cancel();
        }
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
