<?php

namespace App\Filament\Resources;

use App\Filament\Forms\EnglishTranslation;
use App\Filament\Resources\NewsResource\Pages;
use App\Models\News;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class NewsResource extends Resource
{
    protected static ?string $model = News::class;

    protected static ?string $navigationIcon = 'heroicon-o-newspaper';

    protected static ?string $navigationGroup = 'Контент';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Новини';

    protected static ?string $modelLabel = 'новину';

    protected static ?string $pluralModelLabel = 'Новини';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('title')
                ->label('Заголовок')->required()->maxLength(255)->columnSpanFull(),
            Forms\Components\TextInput::make('slug')
                ->label('URL (slug)')->maxLength(255)
                ->helperText('Залиште порожнім - згенерується автоматично.'),
            Forms\Components\Select::make('category_id')
                ->label('Категорія')->relationship('category', 'title')->searchable()->preload(),
            Forms\Components\Textarea::make('excerpt')
                ->label('Короткий опис')->rows(2)->maxLength(1000)->columnSpanFull(),
            Forms\Components\RichEditor::make('body')
                ->label('Текст новини')->columnSpanFull(),
            EnglishTranslation::section(false),
            Forms\Components\FileUpload::make('cover_image')
                ->label('Обкладинка')->image()->directory('news')->imageEditor()->imageResizeMode('contain')->imageResizeTargetWidth('1600')->imageResizeTargetHeight('1600'),
            Forms\Components\DateTimePicker::make('published_at')
                ->label('Дата публікації')->default(now())->seconds(false),
            Forms\Components\Toggle::make('is_published')->label('Опубліковано')->default(true),
            Forms\Components\Toggle::make('is_featured')->label('Рекомендована'),
            Forms\Components\Toggle::make('is_heritage')
                ->label('Урочисте оформлення (heritage)')
                ->helperText('Листоподібний стиль для ювілеїв, історичних та особливих матеріалів.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('cover_image')->label('')->square(),
                Tables\Columns\TextColumn::make('title')->label('Заголовок')->searchable()->limit(50)->weight('bold'),
                Tables\Columns\TextColumn::make('category.title')->label('Категорія')->badge()->sortable(),
                Tables\Columns\TextColumn::make('published_at')->label('Дата')->dateTime('d.m.Y')->sortable(),
                Tables\Columns\TextColumn::make('translation_status')->label('Переклад EN')
                    ->state(fn (News $record) => $record->translationStatus())->badge(),
                Tables\Columns\IconColumn::make('is_published')->label('Опубл.')->boolean(),
                Tables\Columns\IconColumn::make('is_featured')->label('Реком.')->boolean()->toggleable(),
                Tables\Columns\IconColumn::make('is_heritage')->label('Heritage')->boolean()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('views')->label('Перегляди')->numeric()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('likes')->label('Вподобайки')->numeric()->sortable()->toggleable(),
                Tables\Columns\IconColumn::make('telegram_posted_at')->label('TG')->toggleable(isToggledHiddenByDefault: true)
                    ->icon(fn ($state) => $state ? 'heroicon-s-paper-airplane' : 'heroicon-o-minus')
                    ->color(fn ($state) => $state ? 'info' : 'gray')
                    ->tooltip(fn ($state) => $state ? 'Опубліковано в Telegram' : 'Не публікувалось у Telegram'),
            ])
            ->defaultSort('published_at', 'desc')
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
            'index' => Pages\ListNews::route('/'),
            'create' => Pages\CreateNews::route('/create'),
            'edit' => Pages\EditNews::route('/{record}/edit'),
        ];
    }
}
