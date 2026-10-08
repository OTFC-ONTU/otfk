<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use App\Filament\Support\SafeDeleteAction;
use App\Filament\Resources\DepartmentResource\Pages\ListDepartments;
use App\Filament\Resources\DepartmentResource\Pages\CreateDepartment;
use App\Filament\Resources\DepartmentResource\Pages\EditDepartment;
use App\Filament\Forms\Components\HtmlRichEditor;
use App\Filament\Forms\EnglishTranslation;
use App\Filament\Resources\DepartmentResource\Pages;
use App\Filament\Support\ViewOnSite;
use App\Models\Department;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DepartmentResource extends Resource
{
    protected static ?string $model = Department::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-building-office-2';

    protected static string | \UnitEnum | null $navigationGroup = 'Структура та персонал';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Підрозділи';

    protected static ?string $modelLabel = 'підрозділ';

    protected static ?string $pluralModelLabel = 'Підрозділи';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->label('Назва')->required()->maxLength(255)->columnSpanFull(),
            Select::make('type')->label('Тип')->required()->default('kafedra')
                ->options(Department::TYPES)
                ->helperText('Визначає, у якій групі підрозділ показується на сторінці «Структура».'),
            TextInput::make('slug')->label('URL (slug)')->maxLength(255)
                ->prefix(url('/struktura') . '/')
                ->helperText(fn ($record): string => $record?->wasPublic() ? 'Після зміни стара адреса автоматично перенаправлятиме на нову (і на сайті, і в пошуку).' : 'Залиште порожнім - згенерується автоматично.'),
            HtmlRichEditor::make('description')->label('Опис')->columnSpanFull()
                ->helperText('Основний текст на сторінці підрозділу; перші речення видно в його картці на сторінці «Структура».'),
            Toggle::make('is_published')->label('Опубліковано')->default(true)
                ->helperText('Вимкнено — підрозділ і його сторінка не видні на сайті, але лишаються в адмінці.'),

            EnglishTranslation::academicSection(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('translation_status')->label('Переклад EN')
                    ->state(fn (Department $record) => $record->translationStatus())->badge(),
                TextColumn::make('title')->label('Назва')->searchable()->weight('bold')->wrap(),
                TextColumn::make('type')->label('Тип')->badge()
                    ->formatStateUsing(fn ($state) => Department::TYPES[$state] ?? $state),
                TextColumn::make('staff_count')->label('Працівників')->counts('staff')->badge(),
                IconColumn::make('is_published')->label('Опубл.')->boolean(),
                TextColumn::make('sort_order')->label('Порядок')->numeric()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->emptyStateHeading('Підрозділів ще немає')
            ->emptyStateDescription('Підрозділи - це циклові комісії, відділення та служби на сторінці «Структура». Додайте підрозділ, щоб закріплювати за ним працівників.')
            ->recordActions([
                EditAction::make(),
                ViewOnSite::table(fn (Department $record) => route('structure.show', $record)),
            ])
            ->toolbarActions([BulkActionGroup::make([SafeDeleteAction::bulk()])]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDepartments::route('/'),
            'create' => CreateDepartment::route('/create'),
            'edit' => EditDepartment::route('/{record}/edit'),
        ];
    }
}
