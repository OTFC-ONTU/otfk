<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\DocumentCategoryResource\Pages\ListDocumentCategories;
use App\Filament\Resources\DocumentCategoryResource\Pages\CreateDocumentCategory;
use App\Filament\Resources\DocumentCategoryResource\Pages\EditDocumentCategory;
use App\Filament\Forms\EnglishTranslation;
use App\Filament\Resources\DocumentCategoryResource\Pages;
use App\Filament\Support\ViewOnSite;
use App\Models\DocumentCategory;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DocumentCategoryResource extends Resource
{
    protected static ?string $model = DocumentCategory::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-folder';

    protected static string | \UnitEnum | null $navigationGroup = 'Публічна інформація';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Категорії документів';

    protected static ?string $modelLabel = 'категорію';

    protected static ?string $pluralModelLabel = 'Категорії документів';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            EnglishTranslation::section(contentFields: []),
            TextInput::make('title')->label('Назва')->required()->maxLength(255),
            TextInput::make('slug')->label('URL (slug)')->maxLength(255)
                ->prefix(url('/dokumenty') . '/')
                ->helperText('Залиште порожнім - згенерується автоматично.'),
            Select::make('page_id')->label('Сторінка розділу (повний текст замість списку документів)')
                ->relationship('page', 'title')->searchable()->preload()
                ->helperText('Якщо обрано опубліковану сторінку, розділ показує її вміст: текст, таблиці, зображення та файли.'),
            TextInput::make('sort_order')->label('Порядок')->numeric()->default(0)
                ->helperText('Простіше змінити перетягуванням рядків у списку (кнопка «Змінити порядок»).'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('translation_status')->label('Переклад EN')
                    ->state(fn (DocumentCategory $record) => $record->translationStatus())->badge(),
                TextColumn::make('title')->label('Назва')->searchable()->weight('bold'),
                TextColumn::make('documents_count')->label('Документів')->counts('documents')->badge(),
                TextColumn::make('sort_order')->label('Порядок')->numeric()->sortable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->emptyStateHeading('Категорій документів ще немає')
            ->emptyStateDescription('Категорії групують документи на сторінці «Публічна інформація»: установчі документи, звіти, положення тощо. Спершу створіть категорію, потім додавайте в неї документи.')
            ->recordActions([
                EditAction::make(),
                ViewOnSite::table(fn (DocumentCategory $record) => route('documents.category', $record)),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocumentCategories::route('/'),
            'create' => CreateDocumentCategory::route('/create'),
            'edit' => EditDocumentCategory::route('/{record}/edit'),
        ];
    }
}
