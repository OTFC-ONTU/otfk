<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Repeater;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\GalleryResource\Pages\ListGalleries;
use App\Filament\Resources\GalleryResource\Pages\CreateGallery;
use App\Filament\Resources\GalleryResource\Pages\EditGallery;
use App\Filament\Forms\EnglishTranslation;
use App\Filament\Resources\GalleryResource\Pages;
use App\Filament\Support\ViewOnSite;
use App\Models\Gallery;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class GalleryResource extends Resource
{
    protected static ?string $model = Gallery::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-photo';

    protected static string | \UnitEnum | null $navigationGroup = 'Контент';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Фотогалереї';

    protected static ?string $modelLabel = 'галерею';

    protected static ?string $pluralModelLabel = 'Фотогалереї';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            EnglishTranslation::section(contentFields: ['description' => ['label' => 'Англійський опис', 'rows' => 3]])
                ->description('Альбом на /en показується англійською лише після публікації його перекладу та всіх непорожніх підписів фото.'),
            TextInput::make('title')->label('Назва альбому')->required()->maxLength(255)->columnSpanFull(),
            TextInput::make('slug')->label('URL (slug)')->maxLength(255)
                ->prefix(url('/halereya') . '/')
                ->helperText('Залиште порожнім - згенерується автоматично.'),
            DatePicker::make('published_at')->label('Дата')->default(now())
                ->helperText('Дата альбому в картці; новіші альбоми показуються першими.'),
            Textarea::make('description')->label('Опис')->rows(2)->columnSpanFull()
                ->helperText('1-2 речення під назвою альбому. Необовʼязково.'),
            FileUpload::make('cover_image')->label('Обкладинка')->image()->directory('gallery')->imageEditor()->imageResizeMode('contain')->imageResizeTargetWidth('1600')->imageResizeTargetHeight('1600')
                ->helperText('Картка альбому на сторінці «Галерея». Порожнє — використовується перше фото альбому.'),
            Toggle::make('is_published')->label('Опубліковано')->default(true)
                ->helperText('Вимкнено — альбом не видно на сайті, але він лишається в адмінці.'),
            Toggle::make('is_archive')
                ->label('Архівний стиль фото')
                ->helperText('Сепія, рамки та «ламповий» вигляд для історичних альбомів.')
                ->default(false),
            Repeater::make('photos')
                ->relationship()
                ->label('Фотографії')
                ->schema([
                    FileUpload::make('image')->label('Зображення')->image()->directory('gallery')->imageResizeMode('contain')->imageResizeTargetWidth('1600')->imageResizeTargetHeight('1600')->required()->columnSpan(2),
                    TextInput::make('caption')->label('Підпис')->maxLength(255)->columnSpan(2),
                    EnglishTranslation::section(contentFields: [], primaryField: 'caption', primaryLabel: 'Англійський підпис')
                        ->description('Фото без українського підпису не потребує перекладу; стан цього підпису незалежний від стану альбому.'),
                ])
                ->columns(2)
                ->orderColumn('sort_order')
                ->collapsible()
                ->grid(2)
                ->addActionLabel('Додати фото')
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('translation_status')->label('Переклад альбому EN')
                    ->state(fn (Gallery $record) => $record->translationStatus())->badge(),
                ImageColumn::make('cover_image')->label('')->square(),
                TextColumn::make('title')->label('Назва')->searchable()->weight('bold'),
                TextColumn::make('photos_count')->label('Фото')->counts('photos')->badge(),
                TextColumn::make('published_at')->label('Дата')->date('d.m.Y')->sortable(),
                ToggleColumn::make('is_published')->label('Опубл.'),
                IconColumn::make('is_archive')->label('Архів')->boolean()->toggleable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->emptyStateHeading('Фотогалерей ще немає')
            ->emptyStateDescription('Альбоми з фото показуються на сторінці «Галерея». Створіть альбом і додайте в нього фотографії з підписами.')
            ->recordActions([
                EditAction::make(),
                ViewOnSite::table(fn (Gallery $record) => route('galleries.show', $record)),
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
            'index' => ListGalleries::route('/'),
            'create' => CreateGallery::route('/create'),
            'edit' => EditGallery::route('/{record}/edit'),
        ];
    }
}
