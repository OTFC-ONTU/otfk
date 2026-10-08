<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\ProgramResource\Pages\ListPrograms;
use App\Filament\Resources\ProgramResource\Pages\CreateProgram;
use App\Filament\Resources\ProgramResource\Pages\EditProgram;
use App\Filament\Forms\EnglishTranslation;
use App\Filament\Resources\ProgramResource\Pages;
use App\Models\Program;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProgramResource extends Resource
{
    protected static ?string $model = Program::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-document-text';

    protected static string | \UnitEnum | null $navigationGroup = 'Абітурієнту';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Освітні програми';

    protected static ?string $modelLabel = 'програму';

    protected static ?string $pluralModelLabel = 'Освітні програми';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('specialty_id')->label('Спеціальність')
                ->relationship('specialty', 'title')->searchable()->preload()->required(),
            TextInput::make('title')->label('Назва програми')->required()->maxLength(255)->columnSpanFull(),
            FileUpload::make('file_path')->label('Файл програми')->directory('programs')->downloadable()->openable()
                ->acceptedFileTypes(['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                ->maxSize(20480)
                ->helperText('PDF або DOC/DOCX з освітньою програмою (до 20 МБ). Або вкажіть зовнішнє посилання нижче — достатньо одного з двох.'),
            TextInput::make('external_url')->label('Зовнішнє посилання')->url()->maxLength(255)
                ->helperText('Якщо програма розміщена на іншому сайті — замість файла.'),
            Textarea::make('description')->label('Опис')->rows(2)->columnSpanFull()
                ->helperText('Короткий підпис під назвою програми на сторінці спеціальності. Необовʼязково.'),

            EnglishTranslation::academicSection(false, false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('translation_status')->label('Переклад EN')
                    ->state(fn (Program $record) => $record->translationStatus())->badge(),
                TextColumn::make('title')->label('Назва')->searchable()->weight('bold')->wrap(),
                TextColumn::make('specialty.title')->label('Спеціальність')->badge()->sortable(),
                IconColumn::make('file_path')->label('Файл')->boolean()
                    ->getStateUsing(fn ($record) => filled($record->file_path) || filled($record->external_url)),
                TextColumn::make('sort_order')->label('Порядок')->numeric()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->emptyStateHeading('Освітніх програм ще немає')
            ->emptyStateDescription('Освітні програми (файли або посилання) показуються на сторінці своєї спеціальності. Спершу оберіть спеціальність, потім додайте програму.')
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
            'index' => ListPrograms::route('/'),
            'create' => CreateProgram::route('/create'),
            'edit' => EditProgram::route('/{record}/edit'),
        ];
    }
}
