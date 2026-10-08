<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\EventResource\Pages\ListEvents;
use App\Filament\Resources\EventResource\Pages\CreateEvent;
use App\Filament\Resources\EventResource\Pages\EditEvent;
use App\Filament\Forms\EnglishTranslation;
use App\Filament\Resources\EventResource\Pages;
use App\Models\Event;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EventResource extends Resource
{
    protected static ?string $model = Event::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string | \UnitEnum | null $navigationGroup = 'Контент';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Події';

    protected static ?string $modelLabel = 'подію';

    protected static ?string $pluralModelLabel = 'Події';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->label('Назва події')->required()->maxLength(255)->columnSpanFull(),
            DateTimePicker::make('starts_at')->label('Початок')->required()->seconds(false),
            DateTimePicker::make('ends_at')->label('Кінець (необовʼязково)')->seconds(false)
                ->after('starts_at'),
            TextInput::make('location')->label('Місце проведення')->maxLength(255)
                ->placeholder('Актова зала коледжу')->columnSpanFull(),
            Textarea::make('description')->label('Опис')->rows(3)->columnSpanFull()
                ->helperText('Кілька речень у картці події на сторінці «Події»; потрапляє і в подію календаря відвідувача.'),
            TextInput::make('url')->label('Посилання «Детальніше»')->url()->maxLength(255)
                ->helperText('Необовʼязково: новина на сайті або зовнішня сторінка.')->columnSpanFull(),
            Toggle::make('is_published')->label('Опубліковано')->default(true)
                ->helperText('Майбутні опубліковані події видно на головній та на сторінці «Події»; минулі — в архіві подій.'),

            EnglishTranslation::section(contentFields: ['description' => ['label' => 'Англійський опис', 'rows' => 3], 'location' => ['label' => 'Місце проведення англійською']]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('translation_status')->label('Переклад EN')
                    ->state(fn (Event $record) => $record->translationStatus())->badge(),
                TextColumn::make('starts_at')->label('Дата')->dateTime('d.m.Y H:i')->sortable(),
                TextColumn::make('title')->label('Подія')->searchable()->limit(60)->weight('bold'),
                TextColumn::make('location')->label('Місце')->limit(30)->toggleable(),
                IconColumn::make('is_published')->label('Опубл.')->boolean(),
            ])
            ->defaultSort('starts_at', 'desc')
            ->emptyStateHeading('Подій ще немає')
            ->emptyStateDescription('Події показуються в календарі на сторінці «Події»: дні відкритих дверей, конференції, свята. Додайте подію з датою і місцем проведення.')
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
            'index' => ListEvents::route('/'),
            'create' => CreateEvent::route('/create'),
            'edit' => EditEvent::route('/{record}/edit'),
        ];
    }
}
