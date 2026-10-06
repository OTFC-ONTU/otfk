<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BellPeriodResource\Pages;
use App\Models\BellPeriod;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BellPeriodResource extends Resource
{
    protected static ?string $model = BellPeriod::class;

    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';
    protected static ?string $navigationGroup = 'Контент';
    protected static ?int $navigationSort = 6;
    protected static ?string $navigationLabel = 'Розклад дзвінків';
    protected static ?string $modelLabel = 'пару';
    protected static ?string $pluralModelLabel = 'Розклад дзвінків';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('number')->label('Номер пари')->numeric()->required()
                ->minValue(1)->maxValue(10),
            Forms\Components\TimePicker::make('starts')->label('Початок пари')->seconds(false)->required()
                ->helperText('Дзвінок на пару. Перерва — проміжок від кінця попередньої пари до цього часу.'),
            Forms\Components\TimePicker::make('ends')->label('Кінець пари')->seconds(false)->required()
                ->helperText('Дзвінок з пари — початок перерви. Змінюючи ці часи, ви керуєте і перервами.'),
            Forms\Components\Toggle::make('is_active')->label('Активна')->default(true)
                ->helperText('Неактивні пари не показуються на сайті.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')->label('Пара')->sortable()->weight('bold'),
                Tables\Columns\TextColumn::make('starts')->label('Початок')->time('H:i'),
                Tables\Columns\TextColumn::make('ends')->label('Кінець')->time('H:i'),
                Tables\Columns\IconColumn::make('is_active')->label('Активна')->boolean(),
            ])
            ->defaultSort('starts')
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
            'index' => Pages\ListBellPeriods::route('/'),
            'create' => Pages\CreateBellPeriod::route('/create'),
            'edit' => Pages\EditBellPeriod::route('/{record}/edit'),
        ];
    }
}
