<?php

namespace App\Filament\Resources;

use App\Filament\Forms\EnglishTranslation;
use App\Filament\Resources\DepartmentResource\Pages;
use App\Filament\Support\ViewOnSite;
use App\Models\Department;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DepartmentResource extends Resource
{
    protected static ?string $model = Department::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'Структура та персонал';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Підрозділи';

    protected static ?string $modelLabel = 'підрозділ';

    protected static ?string $pluralModelLabel = 'Підрозділи';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('title')->label('Назва')->required()->maxLength(255)->columnSpanFull(),
            Forms\Components\Select::make('type')->label('Тип')->required()->default('kafedra')
                ->options(Department::TYPES)
                ->helperText('Визначає, у якій групі підрозділ показується на сторінці «Структура».'),
            Forms\Components\TextInput::make('slug')->label('URL (slug)')->maxLength(255)
                ->prefix(url('/struktura') . '/')
                ->helperText('Залиште порожнім - згенерується автоматично.'),
            Forms\Components\RichEditor::make('description')->label('Опис')->columnSpanFull()
                ->helperText('Основний текст на сторінці підрозділу; перші речення видно в його картці на сторінці «Структура».'),
            Forms\Components\TextInput::make('sort_order')->label('Порядок')->numeric()->default(0)
                ->helperText('Простіше змінити перетягуванням рядків у списку (кнопка «Змінити порядок»).'),
            Forms\Components\Toggle::make('is_published')->label('Опубліковано')->default(true)
                ->helperText('Вимкнено — підрозділ і його сторінка не видні на сайті, але лишаються в адмінці.'),

            EnglishTranslation::academicSection(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('translation_status')->label('Переклад EN')
                    ->state(fn (Department $record) => $record->translationStatus())->badge(),
                Tables\Columns\TextColumn::make('title')->label('Назва')->searchable()->weight('bold')->wrap(),
                Tables\Columns\TextColumn::make('type')->label('Тип')->badge()
                    ->formatStateUsing(fn ($state) => Department::TYPES[$state] ?? $state),
                Tables\Columns\TextColumn::make('staff_count')->label('Працівників')->counts('staff')->badge(),
                Tables\Columns\IconColumn::make('is_published')->label('Опубл.')->boolean(),
                Tables\Columns\TextColumn::make('sort_order')->label('Порядок')->numeric()->sortable()->toggleable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->emptyStateHeading('Підрозділів ще немає')
            ->emptyStateDescription('Підрозділи - це циклові комісії, відділення та служби на сторінці «Структура». Додайте підрозділ, щоб закріплювати за ним працівників.')
            ->actions([
                Tables\Actions\EditAction::make(),
                ViewOnSite::table(fn (Department $record) => route('structure.show', $record)),
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
            'index' => Pages\ListDepartments::route('/'),
            'create' => Pages\CreateDepartment::route('/create'),
            'edit' => Pages\EditDepartment::route('/{record}/edit'),
        ];
    }
}
