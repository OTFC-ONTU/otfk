<?php

namespace App\Filament\Resources;

use App\Filament\Forms\EnglishTranslation;
use App\Filament\Resources\StatItemResource\Pages;
use App\Models\StatItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class StatItemResource extends Resource
{
    protected static ?string $model = StatItem::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'Контент';

    protected static ?int $navigationSort = 7;

    protected static ?string $navigationLabel = 'Коледж у цифрах';

    protected static ?string $modelLabel = 'цифру';

    protected static ?string $pluralModelLabel = 'Коледж у цифрах';

    public static function form(Form $form): Form
    {
        return $form->schema([
            EnglishTranslation::section(contentFields: [], primaryField: 'label', primaryLabel: 'Англійський підпис'),
            Forms\Components\TextInput::make('label')->label('Підпис')->required()->maxLength(255)
                ->placeholder('Студентів'),
            Forms\Components\TextInput::make('value')->label('Значення')->required()->maxLength(20)
                ->placeholder('1000+')
                ->helperText('Число анімується від нуля. Суфікси «+», «%» тощо зберігаються (напр. 85%).'),
            Forms\Components\TextInput::make('icon')->label('Іконка (heroicon)')->maxLength(100)
                ->placeholder('user-group')
                ->helperText('Назва іконки з heroicons.com (необовʼязково).'),
            Forms\Components\TextInput::make('sort_order')->label('Порядок')->numeric()->default(0),
            Forms\Components\Toggle::make('is_active')->label('Показувати')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('translation_status')->label('Переклад EN')
                    ->state(fn (StatItem $record) => $record->translationStatus())->badge(),
                Tables\Columns\TextColumn::make('label')->label('Підпис')->weight('bold'),
                Tables\Columns\TextColumn::make('value')->label('Значення')->badge()->color('warning'),
                Tables\Columns\TextColumn::make('sort_order')->label('Порядок')->sortable(),
                Tables\Columns\IconColumn::make('is_active')->label('Активна')->boolean(),
            ])
            ->defaultSort('sort_order')
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
            'index' => Pages\ListStatItems::route('/'),
            'create' => Pages\CreateStatItem::route('/create'),
            'edit' => Pages\EditStatItem::route('/{record}/edit'),
        ];
    }
}
