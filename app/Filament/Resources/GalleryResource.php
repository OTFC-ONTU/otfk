<?php

namespace App\Filament\Resources;

use App\Filament\Forms\EnglishTranslation;
use App\Filament\Resources\GalleryResource\Pages;
use App\Filament\Support\ViewOnSite;
use App\Models\Gallery;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class GalleryResource extends Resource
{
    protected static ?string $model = Gallery::class;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationGroup = 'Контент';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Фотогалереї';

    protected static ?string $modelLabel = 'галерею';

    protected static ?string $pluralModelLabel = 'Фотогалереї';

    public static function form(Form $form): Form
    {
        return $form->schema([
            EnglishTranslation::section(contentFields: ['description' => ['label' => 'Англійський опис', 'rows' => 3]])
                ->description('Альбом на /en показується англійською лише після публікації його перекладу та всіх непорожніх підписів фото.'),
            Forms\Components\TextInput::make('title')->label('Назва альбому')->required()->maxLength(255)->columnSpanFull(),
            Forms\Components\TextInput::make('slug')->label('URL (slug)')->maxLength(255)
                ->prefix(url('/halereya') . '/')
                ->helperText('Залиште порожнім - згенерується автоматично.'),
            Forms\Components\DatePicker::make('published_at')->label('Дата')->default(now())
                ->helperText('Дата альбому в картці; новіші альбоми показуються першими.'),
            Forms\Components\Textarea::make('description')->label('Опис')->rows(2)->columnSpanFull()
                ->helperText('1-2 речення під назвою альбому. Необовʼязково.'),
            Forms\Components\FileUpload::make('cover_image')->label('Обкладинка')->image()->directory('gallery')->imageEditor()->imageResizeMode('contain')->imageResizeTargetWidth('1600')->imageResizeTargetHeight('1600')
                ->helperText('Картка альбому на сторінці «Галерея». Порожнє — використовується перше фото альбому.'),
            Forms\Components\Toggle::make('is_published')->label('Опубліковано')->default(true)
                ->helperText('Вимкнено — альбом не видно на сайті, але він лишається в адмінці.'),
            Forms\Components\Toggle::make('is_archive')
                ->label('Архівний стиль фото')
                ->helperText('Сепія, рамки та «ламповий» вигляд для історичних альбомів.')
                ->default(false),
            Forms\Components\Repeater::make('photos')
                ->relationship()
                ->label('Фотографії')
                ->schema([
                    Forms\Components\FileUpload::make('image')->label('Зображення')->image()->directory('gallery')->imageResizeMode('contain')->imageResizeTargetWidth('1600')->imageResizeTargetHeight('1600')->required()->columnSpan(2),
                    Forms\Components\TextInput::make('caption')->label('Підпис')->maxLength(255)->columnSpan(2),
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
                Tables\Columns\TextColumn::make('translation_status')->label('Переклад альбому EN')
                    ->state(fn (Gallery $record) => $record->translationStatus())->badge(),
                Tables\Columns\ImageColumn::make('cover_image')->label('')->square(),
                Tables\Columns\TextColumn::make('title')->label('Назва')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('photos_count')->label('Фото')->counts('photos')->badge(),
                Tables\Columns\TextColumn::make('published_at')->label('Дата')->date('d.m.Y')->sortable(),
                Tables\Columns\ToggleColumn::make('is_published')->label('Опубл.'),
                Tables\Columns\IconColumn::make('is_archive')->label('Архів')->boolean()->toggleable(),
            ])
            ->defaultSort('sort_order')
            ->emptyStateHeading('Фотогалерей ще немає')
            ->emptyStateDescription('Альбоми з фото показуються на сторінці «Галерея». Створіть альбом і додайте в нього фотографії з підписами.')
            ->actions([
                Tables\Actions\EditAction::make(),
                ViewOnSite::table(fn (Gallery $record) => route('galleries.show', $record)),
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
            'index' => Pages\ListGalleries::route('/'),
            'create' => Pages\CreateGallery::route('/create'),
            'edit' => Pages\EditGallery::route('/{record}/edit'),
        ];
    }
}
