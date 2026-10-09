<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Actions\EditAction;
use App\Filament\Resources\StatItemResource\Pages\ListStatItems;
use App\Filament\Resources\StatItemResource\Pages\CreateStatItem;
use App\Filament\Resources\StatItemResource\Pages\EditStatItem;
use App\Filament\Forms\EnglishTranslation;
use App\Filament\Resources\StatItemResource\Pages;
use App\Models\StatItem;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class StatItemResource extends Resource
{
    protected static ?string $model = StatItem::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-chart-bar';

    protected static string | \UnitEnum | null $navigationGroup = 'Головна сторінка';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Коледж у цифрах';

    protected static ?string $modelLabel = 'цифру';

    protected static ?string $pluralModelLabel = 'Коледж у цифрах';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            EnglishTranslation::section(contentFields: [], primaryField: 'label', primaryLabel: 'Англійський підпис'),
            TextInput::make('label')->label('Підпис')->required()->maxLength(255)
                ->placeholder('Студентів'),
            TextInput::make('value')->label('Значення')->required()->maxLength(20)
                ->placeholder('1000+')
                ->helperText('Число анімується від нуля. Суфікси «+», «%» тощо зберігаються (напр. 85%).'),
            TextInput::make('icon')->label('Іконка (heroicon)')->maxLength(100)
                ->placeholder('user-group')
                ->helperText('Назва іконки з heroicons.com (необовʼязково).'),
            Toggle::make('is_active')->label('Показувати')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('translation_status')->label('Переклад EN')
                    ->state(fn (StatItem $record) => $record->translationStatus())->badge(),
                TextColumn::make('label')->label('Підпис')->weight('bold'),
                TextColumn::make('value')->label('Значення')->badge()->color('warning'),
                TextColumn::make('sort_order')->label('Порядок')->sortable()->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')->label('Активна')->boolean(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->emptyStateHeading('Цифр ще немає')
            ->emptyStateDescription('Блок «Коледж у цифрах» на головній сторінці: кількість студентів, викладачів, років історії. Додайте перший показник.')
            ->recordActions([EditAction::make()]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStatItems::route('/'),
            'create' => CreateStatItem::route('/create'),
            'edit' => EditStatItem::route('/{record}/edit'),
        ];
    }
}
