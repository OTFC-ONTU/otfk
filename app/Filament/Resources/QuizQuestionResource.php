<?php

namespace App\Filament\Resources;

use App\Filament\Forms\EnglishTranslation;
use App\Filament\Resources\QuizQuestionResource\Pages;
use App\Models\QuizQuestion;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class QuizQuestionResource extends Resource
{
    protected static ?string $model = QuizQuestion::class;

    protected static ?string $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static ?string $navigationGroup = 'Контент';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Квіз для вступників';

    protected static ?string $modelLabel = 'питання';

    protected static ?string $pluralModelLabel = 'Квіз: яка спеціальність підходить';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('question')->label('Питання')->required()->maxLength(255)->columnSpanFull(),
            Forms\Components\TextInput::make('sort_order')->label('Порядок')->numeric()->default(0),
            Forms\Components\Toggle::make('is_active')->label('Активне')->default(true),
            EnglishTranslation::section(contentFields: [], primaryField: 'question', primaryLabel: 'Англійське питання')
                ->description('Питання на /en показується англійською лише разом з усіма повними опублікованими перекладами варіантів.'),
            Forms\Components\Repeater::make('options')
                ->relationship()
                ->label('Варіанти відповідей')
                ->schema([
                    Forms\Components\TextInput::make('label')->label('Текст варіанта')->required()->maxLength(255)->columnSpan(2),
                    Forms\Components\Select::make('specialty_id')->label('Спеціальність (+бали)')
                        ->relationship('specialty', 'title')->preload()
                        ->helperText('Якій спеціальності зараховуються бали за цей вибір.'),
                    Forms\Components\TextInput::make('points')->label('Балів')->numeric()->default(1)->minValue(1)->maxValue(5),
                    EnglishTranslation::section(contentFields: [], primaryField: 'label', primaryLabel: 'Англійський текст варіанта'),
                ])
                ->columns(2)
                ->orderColumn('sort_order')
                ->defaultItems(4)
                ->minItems(2)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('translation_status')->label('Переклад питання EN')
                    ->state(fn (QuizQuestion $record) => $record->translationStatus())->badge(),
                Tables\Columns\TextColumn::make('sort_order')->label('№')->sortable(),
                Tables\Columns\TextColumn::make('question')->label('Питання')->searchable()->weight('bold')->limit(70),
                Tables\Columns\TextColumn::make('options_count')->counts('options')->label('Варіантів'),
                Tables\Columns\IconColumn::make('is_active')->label('Активне')->boolean(),
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
            'index' => Pages\ListQuizQuestions::route('/'),
            'create' => Pages\CreateQuizQuestion::route('/create'),
            'edit' => Pages\EditQuizQuestion::route('/{record}/edit'),
        ];
    }
}
