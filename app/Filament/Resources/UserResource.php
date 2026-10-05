<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';
    protected static ?string $navigationGroup = 'Налаштування';
    protected static ?int $navigationSort = 9;
    protected static ?string $navigationLabel = 'Адміністратори';
    protected static ?string $modelLabel = 'адміністратор';
    protected static ?string $pluralModelLabel = 'Адміністратори';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('Імʼя')->required()->maxLength(255),
            Forms\Components\TextInput::make('email')->label('Електронна пошта')->email()->required()
                ->maxLength(255)->unique(ignoreRecord: true),

            Forms\Components\TextInput::make('password')->label('Пароль')
                ->password()->revealable()->maxLength(255)
                ->required(fn (string $operation) => $operation === 'create')
                ->dehydrated(fn (?string $state) => filled($state))   // не зберігати, якщо порожнє
                ->confirmed()
                ->helperText('Під час редагування залиште порожнім, щоб не змінювати пароль.'),
            Forms\Components\TextInput::make('password_confirmation')->label('Підтвердження паролю')
                ->password()->revealable()->maxLength(255)
                ->dehydrated(false)
                ->required(fn (string $operation) => $operation === 'create'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Імʼя')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('email')->label('Пошта')->searchable()->copyable()->color('gray'),
                Tables\Columns\TextColumn::make('created_at')->label('Створено')->dateTime('d.m.Y')->sortable(),
            ])
            ->defaultSort('id')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (User $record) => $record->id !== auth()->id()), // не дати видалити себе
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
