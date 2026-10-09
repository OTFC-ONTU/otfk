<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Actions\EditAction;
use App\Filament\Resources\FaqResource\Pages\ListFaqs;
use App\Filament\Resources\FaqResource\Pages\CreateFaq;
use App\Filament\Resources\FaqResource\Pages\EditFaq;
use App\Filament\Forms\EnglishTranslation;
use App\Filament\Resources\FaqResource\Pages;
use App\Models\Faq;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class FaqResource extends Resource
{
    protected static ?string $model = Faq::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static string | \UnitEnum | null $navigationGroup = 'Вступ і навчання';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Питання (FAQ)';

    protected static ?string $modelLabel = 'питання';

    protected static ?string $pluralModelLabel = 'Питання та відповіді';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('question')->label('Питання')->required()->maxLength(255)->columnSpanFull(),
            Textarea::make('answer')->label('Відповідь')->rows(5)->required()->columnSpanFull()
                ->helperText('Звичайний текст; перенесення рядків зберігаються.'),
            Toggle::make('is_active')->label('Показувати')->default(true)
                ->helperText('Вимкнено — питання зникає зі сторінки «Питання та відповіді», але лишається в адмінці.'),

            EnglishTranslation::section(contentFields: ['answer' => ['label' => 'Англійська відповідь', 'rows' => 5]], primaryField: 'question', primaryLabel: 'Англійське питання'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('translation_status')->label('Переклад EN')
                    ->state(fn (Faq $record) => $record->translationStatus())->badge(),
                TextColumn::make('question')->label('Питання')->searchable()->weight('bold')->limit(70),
                TextColumn::make('sort_order')->label('Порядок')->sortable()->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')->label('Активне')->boolean(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->emptyStateHeading('Питань ще немає')
            ->emptyStateDescription('Це розділ «Питання та відповіді» на сторінці /faq. Додайте типові питання вступників і батьків з короткими відповідями.')
            ->recordActions([EditAction::make()]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFaqs::route('/'),
            'create' => CreateFaq::route('/create'),
            'edit' => EditFaq::route('/{record}/edit'),
        ];
    }
}
