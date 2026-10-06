<?php

namespace App\Filament\Resources;

use App\Filament\Forms\EnglishTranslation;
use App\Filament\Resources\EventResource\Pages;
use App\Models\Event;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EventResource extends Resource
{
    protected static ?string $model = Event::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Контент';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Події';

    protected static ?string $modelLabel = 'подію';

    protected static ?string $pluralModelLabel = 'Події';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('title')->label('Назва події')->required()->maxLength(255)->columnSpanFull(),
            Forms\Components\DateTimePicker::make('starts_at')->label('Початок')->required()->seconds(false),
            Forms\Components\DateTimePicker::make('ends_at')->label('Кінець (необовʼязково)')->seconds(false)
                ->after('starts_at'),
            Forms\Components\TextInput::make('location')->label('Місце проведення')->maxLength(255)
                ->placeholder('Актова зала коледжу')->columnSpanFull(),
            Forms\Components\Textarea::make('description')->label('Опис')->rows(3)->columnSpanFull()
                ->helperText('Кілька речень у картці події на сторінці «Події»; потрапляє і в подію календаря відвідувача.'),
            Forms\Components\TextInput::make('url')->label('Посилання «Детальніше»')->url()->maxLength(255)
                ->helperText('Необовʼязково: новина на сайті або зовнішня сторінка.')->columnSpanFull(),
            Forms\Components\Toggle::make('is_published')->label('Опубліковано')->default(true)
                ->helperText('Майбутні опубліковані події видно на головній та на сторінці «Події»; минулі — в архіві подій.'),

            EnglishTranslation::section(contentFields: ['description' => ['label' => 'Англійський опис', 'rows' => 3], 'location' => ['label' => 'Місце проведення англійською']]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('translation_status')->label('Переклад EN')
                    ->state(fn (Event $record) => $record->translationStatus())->badge(),
                Tables\Columns\TextColumn::make('starts_at')->label('Дата')->dateTime('d.m.Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('title')->label('Подія')->searchable()->limit(60)->weight('bold'),
                Tables\Columns\TextColumn::make('location')->label('Місце')->limit(30)->toggleable(),
                Tables\Columns\IconColumn::make('is_published')->label('Опубл.')->boolean(),
            ])
            ->defaultSort('starts_at', 'desc')
            ->emptyStateHeading('Подій ще немає')
            ->emptyStateDescription('Події показуються в календарі на сторінці «Події»: дні відкритих дверей, конференції, свята. Додайте подію з датою і місцем проведення.')
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
            'index' => Pages\ListEvents::route('/'),
            'create' => Pages\CreateEvent::route('/create'),
            'edit' => Pages\EditEvent::route('/{record}/edit'),
        ];
    }
}
