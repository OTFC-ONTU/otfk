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
use App\Filament\Resources\QuizQuestionResource\Pages\ListQuizQuestions;
use App\Filament\Resources\QuizQuestionResource\Pages\CreateQuizQuestion;
use App\Filament\Resources\QuizQuestionResource\Pages\EditQuizQuestion;
use App\Filament\Forms\EnglishTranslation;
use App\Filament\Resources\QuizQuestionResource\Pages;
use App\Models\Program;
use App\Models\QuizQuestion;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class QuizQuestionResource extends Resource
{
    protected static ?string $model = QuizQuestion::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static string | \UnitEnum | null $navigationGroup = 'Вступ і навчання';

    protected static ?int $navigationSort = 3;

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
                    Repeater::make('scores')
                        ->relationship()
                        ->label('Бали освітнім програмам')
                        ->helperText('Один варіант може додавати бали кільком ОПП: 2–3 — головна, 1 — суміжна. Відсоток збігу рахується від найбільшої можливої суми кожної ОПП, тож намагайтеся, щоб усі програми мали приблизно однаковий максимум.')
                        ->schema([
                            Select::make('program_id')->label('ОПП')
                                ->options(fn () => self::programOptions())
                                ->searchable()
                                ->required()
                                ->distinct()
                                ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                ->columnSpan(3),
                            TextInput::make('points')->label('Балів')->numeric()->required()->default(2)->minValue(1)->maxValue(5),
                        ])
                        ->columns(4)
                        ->defaultItems(1)
                        ->addActionLabel('Додати ОПП')
                        ->columnSpanFull(),
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
                TextColumn::make('programs_count')->label('ОПП у питанні')
                    ->state(fn (QuizQuestion $record) => $record->options->flatMap->scores->pluck('program_id')->unique()->count()),
                IconColumn::make('is_active')->label('Активне')->boolean(),
            ])
            ->modifyQueryUsing(fn ($query) => $query->with('options.scores'))
            ->description(fn () => self::coverageSummary())
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->emptyStateHeading('Питань квізу ще немає')
            ->emptyStateDescription('Квіз на сторінці /kviz допомагає вступнику обрати освітню програму: кожен варіант відповіді додає бали одній чи кільком ОПП.')
            ->recordActions([EditAction::make()]);
    }

    /** ОПП, згруповані за спеціальністю: «F7 Комп’ютерна інженерія» => [id => назва]. */
    public static function programOptions(): array
    {
        return once(fn () => Program::query()->with('specialty:id,code,title,sort_order')->get()
            ->sortBy(fn (Program $program) => [$program->specialty?->sort_order, $program->specialty_id, $program->sort_order, $program->id])
            ->groupBy(fn (Program $program) => trim(($program->specialty?->code ?? '').' '.($program->specialty?->title ?? 'Без спеціальності')))
            ->map(fn ($programs) => $programs->mapWithKeys(fn (Program $program) => [$program->id => $program->title])->all())
            ->all());
    }

    /** Підказка над таблицею: чи всі ОПП отримують бали і наскільки рівні їхні максимуми. */
    public static function coverageSummary(): string
    {
        $max = QuizQuestion::maxPoints(QuizQuestion::active()->with('options.scores')->get());

        $programs = Program::query()->whereHas('specialty', fn ($query) => $query->published())->pluck('title', 'id');
        $missing = $programs->except(array_keys($max));
        $range = array_intersect_key($max, $programs->all());

        $text = $range === []
            ? 'Жодна ОПП ще не отримує балів.'
            : 'ОПП з балами: '.count($range).' з '.$programs->count().'; найбільша можлива сума — від '.min($range).' до '.max($range).'.';

        return $missing->isEmpty() ? $text : $text.' Без балів (не можуть стати результатом): '.$missing->implode('; ').'.';
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
