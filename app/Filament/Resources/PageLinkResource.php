<?php

namespace App\Filament\Resources;

use App\Filament\Forms\EnglishTranslation;
use App\Filament\Resources\PageLinkResource\Pages\CreatePageLink;
use App\Filament\Resources\PageLinkResource\Pages\EditPageLink;
use App\Filament\Resources\PageLinkResource\Pages\ListPageLinks;
use App\Filament\Support\ViewOnSite;
use App\Models\Page;
use App\Models\QuickLink;
use App\Rules\SafeUrl;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Кнопки-посилання на сторінках розділів: виглядають як плитки підсторінок, але ведуть
 * за вказаною адресою (форма, зовнішній сервіс) і не створюють окремої сторінки сайту.
 * Записи — у спільній таблиці quick_links (location=page_link).
 */
class PageLinkResource extends Resource
{
    protected static ?string $model = QuickLink::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-link';

    protected static string | \UnitEnum | null $navigationGroup = 'Сторінки та меню';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Кнопки-посилання';

    protected static ?string $modelLabel = 'кнопку-посилання';

    protected static ?string $pluralModelLabel = 'Кнопки-посилання';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('location', QuickLink::LOCATION_PAGE_LINK);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->label('Текст на кнопці')->required()->maxLength(255)->columnSpanFull(),
            Select::make('page_id')->label('Сторінка, де показати кнопку')
                ->relationship('page', 'title', fn (Builder $query) => $query->with('parent.parent.parent')->orderBy('title'))
                ->getOptionLabelFromRecordUsing(fn (Page $page): string => $page->adminOptionLabel())
                ->searchable()->preload()->required()->columnSpanFull()
                ->helperText('У розділі кнопка стане в кінці списку підсторінок, на звичайній сторінці — під текстом.'),
            TextInput::make('url')->label('Посилання')->required()->maxLength(2048)->columnSpanFull()
                ->placeholder('https://… або /novyny')
                ->dehydrateStateUsing(fn (?string $state): string => trim((string) $state))
                ->rule(new SafeUrl)
                // Адреса без схеми («forms.gle/abc») стала б відносною і вела на неіснуючу сторінку сайту
                ->regex('~^\s*(https?://|/|mailto:|tel:)~i')
                ->validationMessages(['regex' => 'Посилання має починатися з https://, http:// або / (сторінка цього сайту).']),
            Toggle::make('open_new_tab')->label('Відкривати у новій вкладці')->default(true),
            Toggle::make('is_visible')->label('Показувати')->default(true),
            EnglishTranslation::section(contentFields: [], primaryLabel: 'Англійський текст на кнопці'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('Текст на кнопці')->searchable()->weight('bold'),
                TextColumn::make('page.title')->label('Сторінка')->badge()->placeholder('Без сторінки'),
                TextColumn::make('url')->label('Посилання')->color('gray')->limit(40),
                TextColumn::make('translation_status')->label('Переклад EN')
                    ->state(fn (QuickLink $record) => $record->translationStatus())->badge(),
                IconColumn::make('open_new_tab')->label('Нова вкладка')->boolean()->toggleable(),
                IconColumn::make('is_visible')->label('Показ')->boolean(),
                TextColumn::make('sort_order')->label('Порядок')->numeric()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            // Порядок кнопок на сторінці — перетягуванням (краще з фільтром «Сторінка»)
            ->reorderable('sort_order')
            ->filters([
                SelectFilter::make('page_id')->label('Сторінка')
                    ->relationship('page', 'title', fn (Builder $query) => $query->with('parent.parent.parent')->whereHas('sectionLinks'))
                    ->getOptionLabelFromRecordUsing(fn (Page $page): string => $page->adminOptionLabel())
                    ->searchable()->preload(),
            ])
            ->emptyStateHeading('Кнопок-посилань ще немає')
            ->emptyStateDescription('Кнопка виглядає як плитка підсторінки, але веде за вказаною адресою, наприклад на Google-форму. Окрема сторінка для неї не потрібна.')
            ->recordActions([
                EditAction::make(),
                ViewOnSite::table(fn (QuickLink $record) => $record->page ? url('/'.$record->page->slug) : null),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPageLinks::route('/'),
            'create' => CreatePageLink::route('/create'),
            'edit' => EditPageLink::route('/{record}/edit'),
        ];
    }
}
