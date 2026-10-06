<?php

namespace App\Filament\Resources;

use App\Filament\Forms\EnglishTranslation;
use App\Filament\Resources\NewsCategoryResource\Pages;
use App\Models\NewsCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class NewsCategoryResource extends Resource
{
    protected static ?string $model = NewsCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Контент';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Категорії новин';

    protected static ?string $modelLabel = 'категорію';

    protected static ?string $pluralModelLabel = 'Категорії новин';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('title')->label('Назва')->required()->maxLength(255),
            Forms\Components\TextInput::make('slug')->label('URL (slug)')->maxLength(255)
                ->helperText('Залиште порожнім - згенерується автоматично.'),
            Forms\Components\TextInput::make('sort_order')->label('Порядок')->numeric()->default(0)
                ->helperText('Порядок категорії у фільтрі на сторінці «Новини»: менше число — раніше.'),
            Forms\Components\Toggle::make('is_heritage')
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
                Tables\Columns\TextColumn::make('translation_status')->label('Переклад EN')
                    ->state(fn (NewsCategory $record) => $record->translationStatus())->badge(),
                Tables\Columns\TextColumn::make('title')->label('Назва')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('slug')->label('URL')->color('gray'),
                Tables\Columns\TextColumn::make('news_count')->label('Новин')->counts('news')->badge(),
                Tables\Columns\IconColumn::make('is_heritage')->label('Heritage')->boolean()->toggleable(),
                Tables\Columns\TextColumn::make('sort_order')->label('Порядок')->numeric()->sortable(),
            ])
            ->defaultSort('sort_order')
            ->emptyStateHeading('Категорій новин ще немає')
            ->emptyStateDescription('Категорії групують новини за темами: «Оголошення», «Події», «Вступ» тощо. За категоріями працює фільтр на сторінці новин.')
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
            'index' => Pages\ListNewsCategories::route('/'),
            'create' => Pages\CreateNewsCategory::route('/create'),
            'edit' => Pages\EditNewsCategory::route('/{record}/edit'),
        ];
    }
}
