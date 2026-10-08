<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\QuizQuestionResource\Pages\ListQuizQuestions;
use App\Filament\Resources\QuizQuestionResource\Pages\CreateQuizQuestion;
use App\Filament\Resources\QuizQuestionResource\Pages\EditQuizQuestion;
use App\Filament\Forms\EnglishTranslation;
use App\Filament\Resources\QuizQuestionResource\Pages;
use App\Models\QuizQuestion;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class QuizQuestionResource extends Resource
{
    protected static ?string $model = QuizQuestion::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static string | \UnitEnum | null $navigationGroup = 'Контент';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Квіз для вступників';

    protected static ?string $modelLabel = 'питання';

    protected static ?string $pluralModelLabel = 'Квіз: яка спеціальність підходить';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('question')->label('Питання')->required()->maxLength(255)->columnSpanFull(),
            Toggle::make('is_active')->label('Активне')->default(true)
                ->helperText('Вимкнено — питання не ставиться у квізі, але лишається в адмінці.'),

            EnglishTranslation::section(contentFields: [], primaryField: 'question', primaryLabel: 'Англійське питання')
                ->description('Питання на /en показується англійською лише разом з усіма повними опублікованими перекладами варіантів.'),
            Repeater::make('options')
                ->relationship()
                ->label('Варіанти відповідей')
                ->schema([
                    TextInput::make('label')->label('Текст варіанта')->required()->maxLength(255)->columnSpan(2),
                    Select::make('specialty_id')->label('Спеціальність (+бали)')
                        ->relationship('specialty', 'title')->preload()
                        ->helperText('Якій спеціальності зараховуються бали за цей вибір.'),
                    TextInput::make('points')->label('Балів')->numeric()->default(1)->minValue(1)->maxValue(5),
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
                TextColumn::make('translation_status')->label('Переклад питання EN')
                    ->state(fn (QuizQuestion $record) => $record->translationStatus())->badge(),
                TextColumn::make('sort_order')->label('№')->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('question')->label('Питання')->searchable()->weight('bold')->limit(70),
                TextColumn::make('options_count')->counts('options')->label('Варіантів'),
                IconColumn::make('is_active')->label('Активне')->boolean(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->emptyStateHeading('Питань квізу ще немає')
            ->emptyStateDescription('Квіз на сторінці /kviz допомагає вступнику обрати спеціальність: кожен варіант відповіді додає бали одній зі спеціальностей.')
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
            'index' => ListQuizQuestions::route('/'),
            'create' => CreateQuizQuestion::route('/create'),
            'edit' => EditQuizQuestion::route('/{record}/edit'),
        ];
    }
}
