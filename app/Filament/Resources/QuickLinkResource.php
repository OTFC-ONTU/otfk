<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\QuickLinkResource\Pages\ListQuickLinks;
use App\Filament\Resources\QuickLinkResource\Pages\CreateQuickLink;
use App\Filament\Resources\QuickLinkResource\Pages\EditQuickLink;
use App\Filament\Forms\EnglishTranslation;
use App\Filament\Resources\QuickLinkResource\Pages;
use App\Models\QuickLink;
use App\Rules\SafeUrl;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Ресурс керує лише плитками головної (location=home_tile). Посилання-партнери
 * підвалу (location=footer_partner) редагуються на сторінці «Налаштування →
 * Підвал і вигляд» — щоб усі частини підвалу жили в одному місці.
 */
class QuickLinkResource extends Resource
{
    protected static ?string $model = QuickLink::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static string | \UnitEnum | null $navigationGroup = 'Контент';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Плитки на головній';

    protected static ?string $modelLabel = 'плитку';

    protected static ?string $pluralModelLabel = 'Плитки на головній';

    /** Доступні іконки для плиток (короткі назви heroicons). */
    public static function iconOptions(): array
    {
        return [
            'academic-cap' => 'Шапка випускника (вступ)',
            'user-group' => 'Студенти',
            'building-library' => 'Будівля / бібліотека',
            'document-text' => 'Документ',
            'newspaper' => 'Новини',
            'photo' => 'Галерея / фото',
            'book-open' => 'Навчання',
            'briefcase' => 'Робота / практика',
            'beaker' => 'Наука / лабораторія',
            'calendar-days' => 'Календар / події',
            'trophy' => 'Досягнення',
            'globe-alt' => 'Дистанційне навчання',
            'map-pin' => 'Контакти / адреса',
            'clipboard-document-list' => 'Список / звіти',
            'users' => 'Люди / колектив',
            'building-office-2' => 'Підрозділ',
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('location', 'home_tile');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            EnglishTranslation::section(contentFields: ['description' => ['label' => 'Англійський опис', 'rows' => 2]]),
            Select::make('location')->label('Розташування')
                ->options([
                    'home_tile' => 'Плитка на головній',
                    'footer_partner' => 'Партнер у підвалі',
                ])
                ->default('home_tile')->required()->live()
                ->helperText('Плитки - 4 кольорові картки під банером. Партнери - посилання в підвалі сайту.'),

            TextInput::make('title')->label('Заголовок')->required()->maxLength(255)->columnSpanFull()
                ->helperText('Плитки - 4 кольорові картки під банером на головній.'),

            Textarea::make('description')->label('Опис')->rows(2)->maxLength(255)->columnSpanFull()
                ->helperText('Короткий підпис під заголовком плитки.'),

            TextInput::make('url')->label('Посилання')->required()->maxLength(255)->rule(new SafeUrl)
                ->placeholder('/abituriyentu або https://...'),

            Select::make('icon')->label('Іконка')
                ->options(static::iconOptions())->searchable()->native(false),

            Select::make('color')->label('Колір')
                ->options(['brand' => 'Синій (фірмовий)', 'gold' => 'Золотий'])
                ->default('brand'),

            Toggle::make('open_new_tab')->label('Відкривати у новій вкладці')->default(false),
            TextInput::make('sort_order')->label('Порядок')->numeric()->default(0)
                ->helperText('Простіше змінити перетягуванням рядків у списку (кнопка «Змінити порядок»).'),
            Toggle::make('is_visible')->label('Показувати')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('translation_status')->label('Переклад EN')
                    ->state(fn (QuickLink $record) => $record->translationStatus())->badge(),
                TextColumn::make('location')->label('Розташування')->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'home_tile' ? 'Плитка' : 'Партнер')
                    ->color(fn (string $state) => $state === 'home_tile' ? 'primary' : 'gray')->sortable(),
                TextColumn::make('title')->label('Заголовок')->searchable()->weight('bold'),
                TextColumn::make('url')->label('Посилання')->color('gray')->limit(30),
                IconColumn::make('is_visible')->label('Показ')->boolean(),
                TextColumn::make('sort_order')->label('Порядок')->numeric()->sortable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->emptyStateHeading('Плиток ще немає')
            ->emptyStateDescription('Плитки - 4 кольорові картки під банером на головній. Посилання-партнери підвалу редагуються в «Налаштування → Підвал і вигляд».')
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQuickLinks::route('/'),
            'create' => CreateQuickLink::route('/create'),
            'edit' => EditQuickLink::route('/{record}/edit'),
        ];
    }
}
