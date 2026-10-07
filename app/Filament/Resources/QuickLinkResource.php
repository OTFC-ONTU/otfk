<?php

namespace App\Filament\Resources;

use App\Filament\Forms\EnglishTranslation;
use App\Filament\Resources\QuickLinkResource\Pages;
use App\Models\QuickLink;
use App\Rules\SafeUrl;
use Filament\Forms;
use Filament\Forms\Form;
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

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationGroup = 'Контент';

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

    public static function form(Form $form): Form
    {
        return $form->schema([
            EnglishTranslation::section(contentFields: ['description' => ['label' => 'Англійський опис', 'rows' => 2]]),
            Forms\Components\Select::make('location')->label('Розташування')
                ->options([
                    'home_tile' => 'Плитка на головній',
                    'footer_partner' => 'Партнер у підвалі',
                ])
                ->default('home_tile')->required()->live()
                ->helperText('Плитки - 4 кольорові картки під банером. Партнери - посилання в підвалі сайту.'),

            Forms\Components\TextInput::make('title')->label('Заголовок')->required()->maxLength(255)->columnSpanFull()
                ->helperText('Плитки - 4 кольорові картки під банером на головній.'),

            Forms\Components\Textarea::make('description')->label('Опис')->rows(2)->maxLength(255)->columnSpanFull()
                ->helperText('Короткий підпис під заголовком плитки.'),

            Forms\Components\TextInput::make('url')->label('Посилання')->required()->maxLength(255)->rule(new SafeUrl)
                ->placeholder('/abituriyentu або https://...'),

            Forms\Components\Select::make('icon')->label('Іконка')
                ->options(static::iconOptions())->searchable()->native(false),

            Forms\Components\Select::make('color')->label('Колір')
                ->options(['brand' => 'Синій (фірмовий)', 'gold' => 'Золотий'])
                ->default('brand'),

            Forms\Components\Toggle::make('open_new_tab')->label('Відкривати у новій вкладці')->default(false),
            Forms\Components\TextInput::make('sort_order')->label('Порядок')->numeric()->default(0)
                ->helperText('Простіше змінити перетягуванням рядків у списку (кнопка «Змінити порядок»).'),
            Forms\Components\Toggle::make('is_visible')->label('Показувати')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('translation_status')->label('Переклад EN')
                    ->state(fn (QuickLink $record) => $record->translationStatus())->badge(),
                Tables\Columns\TextColumn::make('location')->label('Розташування')->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'home_tile' ? 'Плитка' : 'Партнер')
                    ->color(fn (string $state) => $state === 'home_tile' ? 'primary' : 'gray')->sortable(),
                Tables\Columns\TextColumn::make('title')->label('Заголовок')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('url')->label('Посилання')->color('gray')->limit(30),
                Tables\Columns\IconColumn::make('is_visible')->label('Показ')->boolean(),
                Tables\Columns\TextColumn::make('sort_order')->label('Порядок')->numeric()->sortable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->emptyStateHeading('Плиток ще немає')
            ->emptyStateDescription('Плитки - 4 кольорові картки під банером на головній. Посилання-партнери підвалу редагуються в «Налаштування → Підвал і вигляд».')
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
            'index' => Pages\ListQuickLinks::route('/'),
            'create' => Pages\CreateQuickLink::route('/create'),
            'edit' => Pages\EditQuickLink::route('/{record}/edit'),
        ];
    }
}
