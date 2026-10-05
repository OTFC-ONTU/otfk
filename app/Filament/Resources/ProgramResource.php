<?php

namespace App\Filament\Resources;

use App\Filament\Forms\EnglishTranslation;
use App\Filament\Resources\ProgramResource\Pages;
use App\Models\Program;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProgramResource extends Resource
{
    protected static ?string $model = Program::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Абітурієнту';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Освітні програми';

    protected static ?string $modelLabel = 'програму';

    protected static ?string $pluralModelLabel = 'Освітні програми';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('specialty_id')->label('Спеціальність')
                ->relationship('specialty', 'title')->searchable()->preload()->required(),
            Forms\Components\TextInput::make('title')->label('Назва програми')->required()->maxLength(255)->columnSpanFull(),
            Forms\Components\FileUpload::make('file_path')->label('Файл програми')->directory('programs')->downloadable()->openable(),
            Forms\Components\TextInput::make('external_url')->label('Зовнішнє посилання')->url()->maxLength(255),
            Forms\Components\Textarea::make('description')->label('Опис')->rows(2)->columnSpanFull(),
            Forms\Components\TextInput::make('sort_order')->label('Порядок')->numeric()->default(0),
            EnglishTranslation::academicSection(false, false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('translation_status')->label('Переклад EN')
                    ->state(fn (Program $record) => $record->translationStatus())->badge(),
                Tables\Columns\TextColumn::make('title')->label('Назва')->searchable()->weight('bold')->wrap(),
                Tables\Columns\TextColumn::make('specialty.title')->label('Спеціальність')->badge()->sortable(),
                Tables\Columns\IconColumn::make('file_path')->label('Файл')->boolean()
                    ->getStateUsing(fn ($record) => filled($record->file_path) || filled($record->external_url)),
                Tables\Columns\TextColumn::make('sort_order')->label('Порядок')->numeric()->sortable(),
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
            'index' => Pages\ListPrograms::route('/'),
            'create' => Pages\CreateProgram::route('/create'),
            'edit' => Pages\EditProgram::route('/{record}/edit'),
        ];
    }
}
