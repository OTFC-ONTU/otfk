<?php

namespace App\Filament\Resources;

use App\Filament\Forms\EnglishTranslation;
use App\Filament\Resources\SpecialtyResource\Pages;
use App\Models\Specialty;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SpecialtyResource extends Resource
{
    protected static ?string $model = Specialty::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationGroup = 'Абітурієнту';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Спеціальності';

    protected static ?string $modelLabel = 'спеціальність';

    protected static ?string $pluralModelLabel = 'Спеціальності';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Основне')->schema([
                Forms\Components\TextInput::make('title')->label('Назва спеціальності')->required()->maxLength(255)->columnSpanFull(),
                Forms\Components\TextInput::make('code')->label('Код')->maxLength(255)->placeholder('напр., 121'),
                Forms\Components\TextInput::make('slug')->label('URL (slug)')->maxLength(255)
                    ->helperText('Залиште порожнім - згенерується автоматично.'),
                Forms\Components\Textarea::make('short_description')->label('Короткий опис')->rows(2)->columnSpanFull(),
                Forms\Components\RichEditor::make('description')->label('Повний опис')->columnSpanFull(),
                Forms\Components\FileUpload::make('cover_image')->label('Зображення')->image()->directory('specialties')->imageEditor()->imageResizeMode('contain')->imageResizeTargetWidth('1600')->imageResizeTargetHeight('1600')->columnSpanFull(),
            ])->columns(2),
            Forms\Components\Section::make('Деталі навчання')->schema([
                Forms\Components\TextInput::make('degree')->label('Освітній ступінь')->maxLength(255)->placeholder('Фаховий молодший бакалавр'),
                Forms\Components\TextInput::make('study_form')->label('Форма навчання')->maxLength(255)->placeholder('Денна / Заочна'),
                Forms\Components\TextInput::make('duration')->label('Термін навчання')->maxLength(255)->placeholder('3 роки 10 місяців'),
                Forms\Components\TextInput::make('sort_order')->label('Порядок')->numeric()->default(0),
                Forms\Components\Toggle::make('is_published')->label('Опубліковано')->default(true),
            ])->columns(2),
            EnglishTranslation::academicSection(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('translation_status')->label('Переклад EN')
                    ->state(fn (Specialty $record) => $record->translationStatus())->badge(),
                Tables\Columns\ImageColumn::make('cover_image')->label('')->square(),
                Tables\Columns\TextColumn::make('title')->label('Назва')->searchable()->weight('bold')->wrap(),
                Tables\Columns\TextColumn::make('code')->label('Код')->badge()->toggleable(),
                Tables\Columns\TextColumn::make('degree')->label('Ступінь')->toggleable(),
                Tables\Columns\TextColumn::make('programs_count')->label('Програм')->counts('programs')->badge(),
                Tables\Columns\IconColumn::make('is_published')->label('Опубл.')->boolean(),
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
            'index' => Pages\ListSpecialties::route('/'),
            'create' => Pages\CreateSpecialty::route('/create'),
            'edit' => Pages\EditSpecialty::route('/{record}/edit'),
        ];
    }
}
