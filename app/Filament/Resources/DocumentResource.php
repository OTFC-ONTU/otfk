<?php

namespace App\Filament\Resources;

use App\Filament\Forms\EnglishTranslation;
use App\Filament\Resources\DocumentResource\Pages;
use App\Filament\Support\ViewOnSite;
use App\Models\Document;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DocumentResource extends Resource
{
    protected static ?string $model = Document::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-arrow-down';

    protected static ?string $navigationGroup = 'Публічна інформація';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Документи';

    protected static ?string $modelLabel = 'документ';

    protected static ?string $pluralModelLabel = 'Документи';

    public static function form(Form $form): Form
    {
        return $form->schema([
            EnglishTranslation::section(contentFields: ['description' => ['label' => 'Англійський опис', 'rows' => 3]], primaryRows: 2),
            Forms\Components\Select::make('document_category_id')->label('Категорія')
                ->relationship('category', 'title')->searchable()->preload()->required(),
            Forms\Components\Textarea::make('title')->label('Назва документа')->required()->rows(2)->maxLength(2000)->columnSpanFull(),
            Forms\Components\FileUpload::make('file_path')->label('Файл')->directory('documents')
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
            Forms\Components\TextInput::make('external_url')->label('Зовнішнє посилання')->url()->rule(new \App\Rules\SafeUrl)->maxLength(255)
                ->helperText('Якщо документ розміщено на іншому сайті — замість файла.'),
            Forms\Components\Textarea::make('description')->label('Опис')->rows(2)->columnSpanFull()
                ->helperText('Короткий підпис під назвою документа. Необовʼязково.'),
            Forms\Components\DatePicker::make('published_at')->label('Дата документа')->default(now())
                ->helperText('Показується поруч із документом у списку.'),
            Forms\Components\TextInput::make('sort_order')->label('Порядок')->numeric()->default(0)
                ->helperText('Порядок усередині категорії: менше число — вище.'),
            Forms\Components\Toggle::make('is_published')->label('Опубліковано')->default(true)
                ->helperText('Вимкнено — документ зникає зі сторінки «Публічна інформація», але лишається в адмінці.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('translation_status')->label('Переклад EN')
                    ->state(fn (Document $record) => $record->translationStatus())->badge(),
                Tables\Columns\TextColumn::make('title')->label('Назва')->searchable()->weight('bold')->wrap(),
                Tables\Columns\TextColumn::make('category.title')->label('Категорія')->badge()->sortable(),
                Tables\Columns\IconColumn::make('file_path')->label('Файл')->boolean()
                    ->getStateUsing(fn ($record) => filled($record->file_path) || filled($record->external_url)),
                Tables\Columns\TextColumn::make('published_at')->label('Дата')->date('d.m.Y')->sortable(),
                Tables\Columns\ToggleColumn::make('is_published')->label('Опубл.'),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\SelectFilter::make('document_category_id')->label('Категорія')
                    ->relationship('category', 'title')->preload(),
            ])
            ->emptyStateHeading('Документів ще немає')
            ->emptyStateDescription('Документи (PDF, DOC, XLS) показуються на сторінці «Публічна інформація» в своїх категоріях. Завантажте файл або додайте зовнішнє посилання.')
            ->actions([
                Tables\Actions\EditAction::make(),
                ViewOnSite::table(fn (Document $record) => route('documents.category', $record->category))
                    ->visible(fn (Document $record) => $record->category !== null),
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
            'index' => Pages\ListDocuments::route('/'),
            'create' => Pages\CreateDocument::route('/create'),
            'edit' => Pages\EditDocument::route('/{record}/edit'),
        ];
    }
}
