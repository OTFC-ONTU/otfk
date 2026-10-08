<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\MenuItemResource\Pages\ListMenuItems;
use App\Filament\Resources\MenuItemResource\Pages\CreateMenuItem;
use App\Filament\Resources\MenuItemResource\Pages\EditMenuItem;
use App\Filament\Resources\MenuItemResource\Pages;
use App\Filament\Support\ViewOnSite;
use App\Models\MenuItem;
use App\Rules\SafeUrl;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MenuItemResource extends Resource
{
    protected static ?string $model = MenuItem::class;

    /** Структура меню сайту — лише адміністратору (MenuItemPolicy): редактор не може підмінити пункти навігації. */
    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-bars-3';

    protected static string | \UnitEnum | null $navigationGroup = 'Структура сайту';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Меню навігації';

    protected static ?string $modelLabel = 'пункт меню';

    protected static ?string $pluralModelLabel = 'Пункти меню';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('label')->label('Підпис')->required()->maxLength(255),
            TextInput::make('label_en')->label('Підпис англійською')->maxLength(255)
                ->helperText('Необов’язково. Без перекладу використовується словник стандартного меню або український підпис.'),
            Select::make('parent_id')->label('Батьківський пункт')
                ->relationship('parent', 'label', fn ($query) => $query->whereNull('parent_id')->orderBy('sort_order'))
                ->searchable()->preload()
                ->default(fn () => request()->integer('parent') ?: null)
                ->helperText('Залиште порожнім для пункту верхнього рівня. Меню має два рівні: пункт і його підпункти.'),
            Select::make('link_type')->label('Тип посилання')->required()->default('page')
                ->options(['page' => 'Сторінка', 'url' => 'Зовнішнє посилання', 'route' => 'Системний маршрут']),
            Select::make('page_id')->label('Сторінка')
                ->relationship('page', 'title')->searchable()->preload()
                ->helperText('Для типу «Сторінка».'),
            TextInput::make('url')->label('Посилання / назва маршруту')->maxLength(255)->rule(new SafeUrl)
                ->helperText('Для типів «Зовнішнє посилання» (URL) або «Системний маршрут» (напр. home, news.index).'),
            Toggle::make('open_new_tab')->label('Відкривати в новій вкладці'),
            Toggle::make('is_visible')->label('Видимий')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')->label('Підпис')->searchable()->weight('bold'),
                TextColumn::make('children_total')->label('Підпунктів')->badge()->color('gray')
                    ->state(fn (MenuItem $record) => MenuItem::where('parent_id', $record->id)->count())
                    ->visible(fn ($livewire) => ($livewire->activeTab ?? null) === 'top'),
                TextColumn::make('link_type')->label('Тип')->badge()
                    ->formatStateUsing(fn ($state) => ['page' => 'Сторінка', 'url' => 'Посилання', 'route' => 'Маршрут'][$state] ?? $state),
                ToggleColumn::make('is_visible')->label('Видимий'),
                TextColumn::make('sort_order')->label('Порядок')->numeric()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->emptyStateHeading('У цій вкладці поки порожньо')
            ->emptyStateDescription('Пункти меню - це верхня навігація сайту. У вкладці «Верхній рівень» — головні пункти, у вкладці кожного пункту — його підпункти. Кнопка «Створити» одразу підставляє батьківський пункт відкритої вкладки.')
            ->recordActions([
                EditAction::make(),
                ViewOnSite::table(fn (MenuItem $record) => $record->href)
                    ->visible(fn (MenuItem $record) => $record->href !== '#'),
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
            'index' => ListMenuItems::route('/'),
            'create' => CreateMenuItem::route('/create'),
            'edit' => EditMenuItem::route('/{record}/edit'),
        ];
    }
}
