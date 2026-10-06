<?php

namespace App\Filament\Resources;

use App\Filament\Forms\EnglishTranslation;
use App\Filament\Resources\DocumentCategoryResource\Pages;
use App\Filament\Support\ViewOnSite;
use App\Models\DocumentCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DocumentCategoryResource extends Resource
{
    protected static ?string $model = DocumentCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-folder';

    protected static ?string $navigationGroup = 'Публічна інформація';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Категорії документів';

    protected static ?string $modelLabel = 'категорію';

    protected static ?string $pluralModelLabel = 'Категорії документів';

    public static function form(Form $form): Form
    {
        return $form->schema([
            EnglishTranslation::section(contentFields: []),
            Forms\Components\TextInput::make('title')->label('Назва')->required()->maxLength(255),
            Forms\Components\TextInput::make('slug')->label('URL (slug)')->maxLength(255)
                ->prefix(url('/dokumenty') . '/')
                ->helperText('Залиште порожнім - згенерується автоматично.'),
            Forms\Components\TextInput::make('sort_order')->label('Порядок')->numeric()->default(0)
                ->helperText('Простіше змінити перетягуванням рядків у списку (кнопка «Змінити порядок»).'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('translation_status')->label('Переклад EN')
                    ->state(fn (DocumentCategory $record) => $record->translationStatus())->badge(),
                Tables\Columns\TextColumn::make('title')->label('Назва')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('documents_count')->label('Документів')->counts('documents')->badge(),
                Tables\Columns\TextColumn::make('sort_order')->label('Порядок')->numeric()->sortable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->emptyStateHeading('Категорій документів ще немає')
            ->emptyStateDescription('Категорії групують документи на сторінці «Публічна інформація»: установчі документи, звіти, положення тощо. Спершу створіть категорію, потім додавайте в неї документи.')
            ->actions([
                Tables\Actions\EditAction::make(),
                ViewOnSite::table(fn (DocumentCategory $record) => route('documents.category', $record)),
            ])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDocumentCategories::route('/'),
            'create' => Pages\CreateDocumentCategory::route('/create'),
            'edit' => Pages\EditDocumentCategory::route('/{record}/edit'),
        ];
    }
}
