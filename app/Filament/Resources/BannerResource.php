<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Actions\EditAction;
use App\Filament\Resources\BannerResource\Pages\ListBanners;
use App\Filament\Resources\BannerResource\Pages\CreateBanner;
use App\Filament\Resources\BannerResource\Pages\EditBanner;
use App\Filament\Forms\EnglishTranslation;
use App\Filament\Resources\BannerResource\Pages;
use App\Models\Banner;
use App\Rules\SafeUrl;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BannerResource extends Resource
{
    protected static ?string $model = Banner::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-photo';

    protected static string | \UnitEnum | null $navigationGroup = 'Головна сторінка';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Банери';

    protected static ?string $modelLabel = 'банер';

    protected static ?string $pluralModelLabel = 'Банери (головна)';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            EnglishTranslation::section(contentFields: ['subtitle' => ['label' => 'Англійський підзаголовок'], 'image_alt' => ['label' => 'Англійський опис зображення (alt)'], 'link_label' => ['label' => 'Англійський текст кнопки']], optionalPrimary: true),
            TextInput::make('title')->label('Заголовок')->maxLength(255)->columnSpanFull(),
            TextInput::make('subtitle')->label('Підзаголовок')->maxLength(255)->columnSpanFull(),
            FileUpload::make('image')->label('Зображення')->image()->directory('banners')->imageEditor()->imageResizeMode('contain')->imageResizeTargetWidth('1920')->imageResizeTargetHeight('1080')
                ->helperText('Якщо не завантажити - буде синій градієнт. Після збереження створюється WebP-версія.')->columnSpanFull(),
            TextInput::make('image_alt')->label('Опис зображення (alt)')
                ->maxLength(255)->columnSpanFull()
                ->helperText('Для доступності та SEO. Якщо порожньо — використається заголовок банера.'),
            TextInput::make('link_url')->label('Посилання')->maxLength(255)->placeholder('/abituriyentu')->rule(new SafeUrl)
                ->helperText('Куди веде кнопка банера. Порожнє — банер без кнопки.'),
            TextInput::make('link_label')->label('Текст кнопки')->maxLength(255)->placeholder('Детальніше'),
            DatePicker::make('starts_at')->label('Показувати з')
                ->helperText('Порожні дати — банер показується постійно.'),
            DatePicker::make('ends_at')->label('Показувати до'),
            Toggle::make('is_published')->label('Активний')->default(true)
                ->helperText('Вимкнено — банер прибирається з головної, але лишається в адмінці.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('translation_status')->label('Переклад EN')
                    ->state(fn (Banner $record) => $record->translationStatus())->badge(),
                ImageColumn::make('image')->label('')->square(),
                TextColumn::make('title')->label('Заголовок')->searchable()->weight('bold'),
                IconColumn::make('is_published')->label('Активний')->boolean(),
                TextColumn::make('sort_order')->label('Порядок')->numeric()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->emptyStateHeading('Банерів ще немає')
            ->emptyStateDescription('Банери - великі слайди у верхній частині головної сторінки. Без жодного активного банера показується стандартна синя заставка.')
            ->recordActions([EditAction::make()]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBanners::route('/'),
            'create' => CreateBanner::route('/create'),
            'edit' => EditBanner::route('/{record}/edit'),
        ];
    }
}
