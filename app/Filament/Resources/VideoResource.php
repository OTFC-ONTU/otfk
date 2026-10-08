<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\VideoResource\Pages\ListVideos;
use App\Filament\Resources\VideoResource\Pages\CreateVideo;
use App\Filament\Resources\VideoResource\Pages\EditVideo;
use App\Filament\Forms\EnglishTranslation;
use App\Filament\Resources\VideoResource\Pages;
use App\Models\Video;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class VideoResource extends Resource
{
    protected static ?string $model = Video::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-play-circle';

    protected static string | \UnitEnum | null $navigationGroup = 'Контент';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Відео';

    protected static ?string $modelLabel = 'відео';

    protected static ?string $pluralModelLabel = 'Відео';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            EnglishTranslation::section(contentFields: ['description' => ['label' => 'Англійський опис', 'rows' => 3]]),
            TextInput::make('title')->label('Назва')->required()->maxLength(255)->columnSpanFull(),
            TextInput::make('youtube_id')->label('Посилання на YouTube або ID')->required()->maxLength(255)
                ->helperText('Просто вставте посилання (youtube.com/watch?v=…, youtu.be/…, shorts) — ID збережеться сам.')
                ->dehydrateStateUsing(fn (?string $state) => static::extractYoutubeId((string) $state)),
            DatePicker::make('published_at')->label('Дата')->default(now())
                ->helperText('Показується підписом під відео на сторінці «Відео».'),
            Textarea::make('description')->label('Опис')->rows(3)->columnSpanFull()
                ->helperText('Кілька речень під назвою відео. Необовʼязково.'),
            Toggle::make('is_published')->label('Опубліковано')->default(true)
                ->helperText('Вимкнено — відео зникає зі сторінки «Відео», але лишається в адмінці.'),
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
                TextColumn::make('translation_status')->label('Переклад EN')
                    ->state(fn (Video $record) => $record->translationStatus())->badge(),
                ImageColumn::make('youtube_id')->label('')->square()
                    ->getStateUsing(fn ($record) => "https://img.youtube.com/vi/{$record->youtube_id}/default.jpg"),
                TextColumn::make('title')->label('Назва')->searchable()->weight('bold'),
                TextColumn::make('published_at')->label('Дата')->date('d.m.Y')->sortable(),
                IconColumn::make('is_published')->label('Опубл.')->boolean(),
                TextColumn::make('sort_order')->label('Порядок')->numeric()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->emptyStateHeading('Відео ще немає')
            ->emptyStateDescription('Відео з YouTube показуються на сторінці «Відео». Просто вставте посилання на ролик - обкладинка підтягнеться автоматично.')
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
            'index' => ListVideos::route('/'),
            'create' => CreateVideo::route('/create'),
            'edit' => EditVideo::route('/{record}/edit'),
        ];
    }
}
