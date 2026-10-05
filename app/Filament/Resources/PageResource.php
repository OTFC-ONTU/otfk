<?php

namespace App\Filament\Resources;

use App\Filament\Forms\EnglishTranslation;
use App\Filament\Resources\PageResource\Pages;
use App\Models\Page;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Структура сайту';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Сторінки';

    protected static ?string $modelLabel = 'сторінку';

    protected static ?string $pluralModelLabel = 'Сторінки';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Контент')->schema([
                Forms\Components\TextInput::make('title')->label('Назва сторінки')->required()->maxLength(255)->columnSpanFull(),
                Forms\Components\TextInput::make('slug')->label('URL (slug)')->maxLength(255)
                    ->helperText('Залиште порожнім - згенерується автоматично.'),
                Forms\Components\Select::make('parent_id')->label('Батьківський розділ')
                    ->relationship('parent', 'title')->searchable()->preload(),
                Forms\Components\Textarea::make('excerpt')->label('Короткий опис')->rows(2)->columnSpanFull(),
                Forms\Components\Toggle::make('is_heritage')
                    ->label('Урочисте оформлення (heritage)')
                    ->helperText('Увімкніть для сторінок історії, хроніки та ювілейних матеріалів — стиль «листа» на сайті.')
                    ->columnSpanFull(),
                Forms\Components\RichEditor::make('body')->label('Основний текст')->columnSpanFull(),
                Forms\Components\FileUpload::make('cover_image')->label('Зображення')->image()->directory('pages')->imageEditor()->imageResizeMode('contain')->imageResizeTargetWidth('1600')->imageResizeTargetHeight('1600')->columnSpanFull(),
            ])->columns(2),
            EnglishTranslation::section(true),
            Forms\Components\Section::make('Налаштування')->schema([
                Forms\Components\Toggle::make('is_published')->label('Опубліковано')->default(true),
                Forms\Components\TextInput::make('sort_order')->label('Порядок')->numeric()->default(0),
                Forms\Components\TextInput::make('section')->label('Розділ (службове поле)')->maxLength(255),
                Forms\Components\TextInput::make('meta_title')->label('SEO-заголовок')->maxLength(255),
                Forms\Components\Textarea::make('meta_description')->label('SEO-опис')->rows(2)->maxLength(500)->columnSpanFull(),
            ])->columns(2)->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->label('Назва')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('parent.title')->label('Розділ')->badge()->placeholder('-')->sortable(),
                Tables\Columns\TextColumn::make('slug')->label('URL')->color('gray')->toggleable(),
                Tables\Columns\TextColumn::make('translation_status')->label('Переклад EN')
                    ->state(fn (Page $record) => $record->translationStatus())->badge(),
                Tables\Columns\IconColumn::make('is_published')->label('Опубл.')->boolean(),
                Tables\Columns\IconColumn::make('is_heritage')->label('Heritage')->boolean()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('sort_order')->label('Порядок')->numeric()->sortable()->toggleable(),
            ])
            ->defaultSort('title')
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
            'index' => Pages\ListPages::route('/'),
            'create' => Pages\CreatePage::route('/create'),
            'edit' => Pages\EditPage::route('/{record}/edit'),
        ];
    }
}
