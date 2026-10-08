<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use App\Rules\SafeUrl;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\DocumentResource\Pages\ListDocuments;
use App\Filament\Resources\DocumentResource\Pages\CreateDocument;
use App\Filament\Resources\DocumentResource\Pages\EditDocument;
use App\Filament\Forms\EnglishTranslation;
use App\Filament\Resources\DocumentResource\Pages;
use App\Filament\Support\ViewOnSite;
use App\Models\Document;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DocumentResource extends Resource
{
    protected static ?string $model = Document::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-document-arrow-down';

    protected static string | \UnitEnum | null $navigationGroup = 'Публічна інформація';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Документи';

    protected static ?string $modelLabel = 'документ';

    protected static ?string $pluralModelLabel = 'Документи';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            EnglishTranslation::section(contentFields: ['description' => ['label' => 'Англійський опис', 'rows' => 3]], primaryRows: 2),
            Select::make('document_category_id')->label('Категорія')
                ->relationship('category', 'title')->searchable()->preload()->required(),
            Textarea::make('title')->label('Назва документа')->required()->rows(2)->maxLength(2000)->columnSpanFull(),
            FileUpload::make('file_path')->label('Файл')->directory('documents')
                ->downloadable()->openable()
                ->acceptedFileTypes([
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ])
                ->maxSize(20480)
                ->helperText('PDF, DOC(X), XLS(X), до 20 МБ. Або вкажіть зовнішнє посилання нижче.'),
            TextInput::make('external_url')->label('Зовнішнє посилання')->url()->rule(new SafeUrl)->maxLength(255)
                ->helperText('Якщо документ розміщено на іншому сайті — замість файла.'),
            Textarea::make('description')->label('Опис')->rows(2)->columnSpanFull()
                ->helperText('Короткий підпис під назвою документа. Необовʼязково.'),
            DatePicker::make('published_at')->label('Дата документа')->default(now())
                ->helperText('Показується поруч із документом у списку.'),
            TextInput::make('sort_order')->label('Порядок')->numeric()->default(0)
                ->helperText('Порядок усередині категорії: менше число — вище.'),
            Toggle::make('is_published')->label('Опубліковано')->default(true)
                ->helperText('Вимкнено — документ зникає зі сторінки «Публічна інформація», але лишається в адмінці.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('translation_status')->label('Переклад EN')
                    ->state(fn (Document $record) => $record->translationStatus())->badge(),
                TextColumn::make('title')->label('Назва')->searchable()->weight('bold')->wrap(),
                TextColumn::make('category.title')->label('Категорія')->badge()->sortable(),
                IconColumn::make('file_path')->label('Файл')->boolean()
                    ->getStateUsing(fn ($record) => filled($record->file_path) || filled($record->external_url)),
                TextColumn::make('published_at')->label('Дата')->date('d.m.Y')->sortable(),
                ToggleColumn::make('is_published')->label('Опубл.'),
            ])
            ->defaultSort('sort_order')
            ->filters([
                SelectFilter::make('document_category_id')->label('Категорія')
                    ->relationship('category', 'title')->preload(),
            ])
            ->emptyStateHeading('Документів ще немає')
            ->emptyStateDescription('Документи (PDF, DOC, XLS) показуються на сторінці «Публічна інформація» в своїх категоріях. Завантажте файл або додайте зовнішнє посилання.')
            ->recordActions([
                EditAction::make(),
                ViewOnSite::table(fn (Document $record) => route('documents.category', $record->category))
                    ->visible(fn (Document $record) => $record->category !== null),
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
            'index' => ListDocuments::route('/'),
            'create' => CreateDocument::route('/create'),
            'edit' => EditDocument::route('/{record}/edit'),
        ];
    }
}
