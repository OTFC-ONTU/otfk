<?php

namespace App\Filament\Resources;

use App\Filament\Forms\EnglishTranslation;
use App\Filament\Resources\DepartmentResource\Pages;
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
                ->options(Department::TYPES),
            Forms\Components\TextInput::make('slug')->label('URL (slug)')->maxLength(255)
                ->helperText('Залиште порожнім - згенерується автоматично.'),
            Forms\Components\RichEditor::make('description')->label('Опис')->columnSpanFull(),
            Forms\Components\TextInput::make('sort_order')->label('Порядок')->numeric()->default(0),
            Forms\Components\Toggle::make('is_published')->label('Опубліковано')->default(true),
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
            'index' => Pages\ListDepartments::route('/'),
            'create' => Pages\CreateDepartment::route('/create'),
            'edit' => Pages\EditDepartment::route('/{record}/edit'),
        ];
    }
}
