<?php

namespace App\Filament\Resources;

use App\Filament\Forms\EnglishTranslation;
use App\Filament\Resources\FaqResource\Pages;
use App\Models\Faq;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class FaqResource extends Resource
{
    protected static ?string $model = Faq::class;

    protected static ?string $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static ?string $navigationGroup = 'Контент';

    protected static ?int $navigationSort = 9;

    protected static ?string $navigationLabel = 'Питання (FAQ)';

    protected static ?string $modelLabel = 'питання';

    protected static ?string $pluralModelLabel = 'Питання та відповіді';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('question')->label('Питання')->required()->maxLength(255)->columnSpanFull(),
            Forms\Components\Textarea::make('answer')->label('Відповідь')->rows(5)->required()->columnSpanFull()
                ->helperText('Звичайний текст; перенесення рядків зберігаються.'),
            Forms\Components\TextInput::make('sort_order')->label('Порядок')->numeric()->default(0)
                ->helperText('Простіше змінити перетягуванням рядків у списку (кнопка «Змінити порядок»).'),
            Forms\Components\Toggle::make('is_active')->label('Показувати')->default(true)
                ->helperText('Вимкнено — питання зникає зі сторінки «Питання та відповіді», але лишається в адмінці.'),

            EnglishTranslation::section(contentFields: ['answer' => ['label' => 'Англійська відповідь', 'rows' => 5]], primaryField: 'question', primaryLabel: 'Англійське питання'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('translation_status')->label('Переклад EN')
                    ->state(fn (Faq $record) => $record->translationStatus())->badge(),
                Tables\Columns\TextColumn::make('question')->label('Питання')->searchable()->weight('bold')->limit(70),
                Tables\Columns\TextColumn::make('sort_order')->label('Порядок')->sortable(),
                Tables\Columns\IconColumn::make('is_active')->label('Активне')->boolean(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->emptyStateHeading('Питань ще немає')
            ->emptyStateDescription('Це розділ «Питання та відповіді» на сторінці /faq. Додайте типові питання вступників і батьків з короткими відповідями.')
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
            'index' => Pages\ListFaqs::route('/'),
            'create' => Pages\CreateFaq::route('/create'),
            'edit' => Pages\EditFaq::route('/{record}/edit'),
        ];
    }
}
