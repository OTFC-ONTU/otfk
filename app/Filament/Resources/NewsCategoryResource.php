<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\NewsCategoryResource\Pages\ListNewsCategories;
use App\Filament\Resources\NewsCategoryResource\Pages\CreateNewsCategory;
use App\Filament\Resources\NewsCategoryResource\Pages\EditNewsCategory;
use App\Filament\Forms\EnglishTranslation;
use App\Filament\Resources\NewsCategoryResource\Pages;
use App\Models\NewsCategory;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class NewsCategoryResource extends Resource
{
    protected static ?string $model = NewsCategory::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-tag';

    protected static string | \UnitEnum | null $navigationGroup = 'Контент';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Категорії новин';

    protected static ?string $modelLabel = 'категорію';

    protected static ?string $pluralModelLabel = 'Категорії новин';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->label('Назва')->required()->maxLength(255),
            TextInput::make('slug')->label('URL (slug)')->maxLength(255)
                ->helperText('Залиште порожнім - згенерується автоматично.'),
            TextInput::make('sort_order')->label('Порядок')->numeric()->default(0)
                ->helperText('Порядок категорії у фільтрі на сторінці «Новини»: менше число — раніше.'),
            Toggle::make('is_heritage')
                ->label('Heritage-стиль для всіх новин категорії')
                ->helperText('Урочисте листоподібне оформлення для архіву, історії, ювілеїв. Можна вимкнути окремо в новині.')
                ->default(false),
            EnglishTranslation::section(contentFields: []),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('translation_status')->label('Переклад EN')
                    ->state(fn (NewsCategory $record) => $record->translationStatus())->badge(),
                TextColumn::make('title')->label('Назва')->searchable()->weight('bold'),
                TextColumn::make('slug')->label('URL')->color('gray'),
                TextColumn::make('news_count')->label('Новин')->counts('news')->badge(),
                IconColumn::make('is_heritage')->label('Heritage')->boolean()->toggleable(),
                TextColumn::make('sort_order')->label('Порядок')->numeric()->sortable(),
            ])
            ->defaultSort('sort_order')
            ->emptyStateHeading('Категорій новин ще немає')
            ->emptyStateDescription('Категорії групують новини за темами: «Оголошення», «Події», «Вступ» тощо. За категоріями працює фільтр на сторінці новин.')
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
            'index' => ListNewsCategories::route('/'),
            'create' => CreateNewsCategory::route('/create'),
            'edit' => EditNewsCategory::route('/{record}/edit'),
        ];
    }
}
