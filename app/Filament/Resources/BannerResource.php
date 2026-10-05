<?php

namespace App\Filament\Resources;

use App\Filament\Forms\EnglishTranslation;
use App\Filament\Resources\BannerResource\Pages;
use App\Models\Banner;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BannerResource extends Resource
{
    protected static ?string $model = Banner::class;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationGroup = 'Контент';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Банери';

    protected static ?string $modelLabel = 'банер';

    protected static ?string $pluralModelLabel = 'Банери (головна)';

    public static function form(Form $form): Form
    {
        return $form->schema([
            EnglishTranslation::section(contentFields: ['subtitle' => ['label' => 'Англійський підзаголовок'], 'image_alt' => ['label' => 'Англійський опис зображення (alt)'], 'link_label' => ['label' => 'Англійський текст кнопки']], optionalPrimary: true),
            Forms\Components\TextInput::make('title')->label('Заголовок')->maxLength(255)->columnSpanFull(),
            Forms\Components\TextInput::make('subtitle')->label('Підзаголовок')->maxLength(255)->columnSpanFull(),
            Forms\Components\FileUpload::make('image')->label('Зображення')->image()->directory('banners')->imageEditor()->imageResizeMode('contain')->imageResizeTargetWidth('1920')->imageResizeTargetHeight('1080')
                ->helperText('Якщо не завантажити - буде синій градієнт. Після збереження створюється WebP-версія.')->columnSpanFull(),
            Forms\Components\TextInput::make('image_alt')->label('Опис зображення (alt)')
                ->maxLength(255)->columnSpanFull()
                ->helperText('Для доступності та SEO. Якщо порожньо — використається заголовок банера.'),
            Forms\Components\TextInput::make('link_url')->label('Посилання')->maxLength(255)->placeholder('/abituriyentu'),
            Forms\Components\TextInput::make('link_label')->label('Текст кнопки')->maxLength(255)->placeholder('Детальніше'),
            Forms\Components\DatePicker::make('starts_at')->label('Показувати з'),
            Forms\Components\DatePicker::make('ends_at')->label('Показувати до'),
            Forms\Components\TextInput::make('sort_order')->label('Порядок')->numeric()->default(0),
            Forms\Components\Toggle::make('is_published')->label('Активний')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('translation_status')->label('Переклад EN')
                    ->state(fn (Banner $record) => $record->translationStatus())->badge(),
                Tables\Columns\ImageColumn::make('image')->label('')->square(),
                Tables\Columns\TextColumn::make('title')->label('Заголовок')->searchable()->weight('bold'),
                Tables\Columns\IconColumn::make('is_published')->label('Активний')->boolean(),
                Tables\Columns\TextColumn::make('sort_order')->label('Порядок')->numeric()->sortable(),
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
            'index' => Pages\ListBanners::route('/'),
            'create' => Pages\CreateBanner::route('/create'),
            'edit' => Pages\EditBanner::route('/{record}/edit'),
        ];
    }
}
