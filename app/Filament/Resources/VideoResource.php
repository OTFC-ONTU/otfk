<?php

namespace App\Filament\Resources;

use App\Filament\Forms\EnglishTranslation;
use App\Filament\Resources\VideoResource\Pages;
use App\Models\Video;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class VideoResource extends Resource
{
    protected static ?string $model = Video::class;

    protected static ?string $navigationIcon = 'heroicon-o-play-circle';

    protected static ?string $navigationGroup = 'Контент';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Відео';

    protected static ?string $modelLabel = 'відео';

    protected static ?string $pluralModelLabel = 'Відео';

    public static function form(Form $form): Form
    {
        return $form->schema([
            EnglishTranslation::section(contentFields: ['description' => ['label' => 'Англійський опис', 'rows' => 3]]),
            Forms\Components\TextInput::make('title')->label('Назва')->required()->maxLength(255)->columnSpanFull(),
            Forms\Components\TextInput::make('youtube_id')->label('Посилання на YouTube або ID')->required()->maxLength(255)
                ->helperText('Просто вставте посилання (youtube.com/watch?v=…, youtu.be/…, shorts) — ID збережеться сам.')
                ->dehydrateStateUsing(fn (?string $state) => static::extractYoutubeId((string) $state)),
            Forms\Components\DatePicker::make('published_at')->label('Дата')->default(now()),
            Forms\Components\Textarea::make('description')->label('Опис')->rows(3)->columnSpanFull(),
            Forms\Components\TextInput::make('sort_order')->label('Порядок')->numeric()->default(0),
            Forms\Components\Toggle::make('is_published')->label('Опубліковано')->default(true),
        ]);
    }

    /** Дістає ID відео з будь-якого формату посилання YouTube (або повертає введений ID як є). */
    public static function extractYoutubeId(string $input): string
    {
        $input = trim($input);

        foreach ([
            '#(?:youtube\.com|youtube-nocookie\.com)/(?:watch\?(?:[^"]*&)?v=|embed/|shorts/|live/)([A-Za-z0-9_-]{6,})#u',
            '#youtu\.be/([A-Za-z0-9_-]{6,})#u',
        ] as $pattern) {
            if (preg_match($pattern, $input, $m)) {
                return $m[1];
            }
        }

        return $input;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('translation_status')->label('Переклад EN')
                    ->state(fn (Video $record) => $record->translationStatus())->badge(),
                Tables\Columns\ImageColumn::make('youtube_id')->label('')->square()
                    ->getStateUsing(fn ($record) => "https://img.youtube.com/vi/{$record->youtube_id}/default.jpg"),
                Tables\Columns\TextColumn::make('title')->label('Назва')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('published_at')->label('Дата')->date('d.m.Y')->sortable(),
                Tables\Columns\IconColumn::make('is_published')->label('Опубл.')->boolean(),
                Tables\Columns\TextColumn::make('sort_order')->label('Порядок')->numeric()->sortable()->toggleable(),
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
            'index' => Pages\ListVideos::route('/'),
            'create' => Pages\CreateVideo::route('/create'),
            'edit' => Pages\EditVideo::route('/{record}/edit'),
        ];
    }
}
