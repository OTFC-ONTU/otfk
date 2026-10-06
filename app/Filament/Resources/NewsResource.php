<?php

namespace App\Filament\Resources;

use App\Filament\Forms\EnglishTranslation;
use App\Filament\Resources\NewsResource\Pages;
use App\Filament\Support\ViewOnSite;
use App\Models\News;
use App\Support\UniqueSlug;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class NewsResource extends Resource
{
    protected static ?string $model = News::class;

    protected static ?string $navigationIcon = 'heroicon-o-newspaper';

    protected static ?string $navigationGroup = 'Контент';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Новини';

    protected static ?string $modelLabel = 'новину';

    protected static ?string $pluralModelLabel = 'Новини';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('title')
                ->label('Заголовок')->required()->maxLength(255)->columnSpanFull(),
            Forms\Components\TextInput::make('slug')
                ->label('URL (slug)')->maxLength(255)
                ->prefix(url('/novyny') . '/')
                ->helperText('Залиште порожнім - згенерується автоматично.'),
            Forms\Components\Select::make('category_id')
                ->label('Категорія')->relationship('category', 'title')->searchable()->preload()
                ->helperText('Необовʼязково. За категоріями працює фільтр на сторінці «Новини».'),
            Forms\Components\Textarea::make('excerpt')
                ->label('Короткий опис')->rows(2)->maxLength(1000)->columnSpanFull()
                ->helperText('1-2 речення: показується в картці новини у списку та при поширенні в соцмережах і месенджерах.'),
            Forms\Components\RichEditor::make('body')
                ->label('Текст новини')
                ->fileAttachmentsDisk('public')
                ->fileAttachmentsDirectory('news')
                ->fileAttachmentsVisibility('public')
                ->helperText('Зображення можна вставляти просто в текст — кнопкою прикріплення в редакторі.')
                ->columnSpanFull(),

            EnglishTranslation::section(false),
            Forms\Components\FileUpload::make('cover_image')
                ->label('Обкладинка')->image()->directory('news')->imageEditor()->imageResizeMode('contain')->imageResizeTargetWidth('1600')->imageResizeTargetHeight('1600')
                ->helperText('Горизонтальне фото: показується в картці у списку новин і вгорі самої новини.'),
            Forms\Components\DateTimePicker::make('published_at')
                ->label('Дата публікації')->default(now())->seconds(false)
                ->helperText('Новина з майбутньою датою зʼявиться на сайті лише коли дата настане.'),
            Forms\Components\Toggle::make('is_published')->label('Опубліковано')->default(true)
                ->helperText('Вимкнено — чернетка: на сайті не видно, лишається в адмінці та у віджеті «Чернетки».'),
            Forms\Components\Toggle::make('is_featured')->label('Рекомендована')
                ->helperText('Службова позначка «на майбутнє» — на сайті поки не використовується.'),
            Forms\Components\Toggle::make('is_heritage')
                ->label('Урочисте оформлення (heritage)')
                ->helperText('Листоподібний стиль для ювілеїв, історичних та особливих матеріалів.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('cover_image')->label('')->square(),
                Tables\Columns\TextColumn::make('title')->label('Заголовок')->searchable()->limit(50)->weight('bold'),
                Tables\Columns\TextColumn::make('category.title')->label('Категорія')->badge()->sortable(),
                Tables\Columns\TextColumn::make('published_at')->label('Дата')->dateTime('d.m.Y')->sortable(),
                Tables\Columns\TextColumn::make('translation_status')->label('Переклад EN')
                    ->state(fn (News $record) => $record->translationStatus())->badge(),
                Tables\Columns\IconColumn::make('is_published')->label('Опубл.')->boolean(),
                Tables\Columns\IconColumn::make('is_featured')->label('Реком.')->boolean()->toggleable(),
                Tables\Columns\IconColumn::make('is_heritage')->label('Heritage')->boolean()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('views')->label('Перегляди')->numeric()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('likes')->label('Вподобайки')->numeric()->sortable()->toggleable(),
                Tables\Columns\IconColumn::make('telegram_posted_at')->label('TG')->toggleable(isToggledHiddenByDefault: true)
                    ->icon(fn ($state) => $state ? 'heroicon-s-paper-airplane' : 'heroicon-o-minus')
                    ->color(fn ($state) => $state ? 'info' : 'gray')
                    ->tooltip(fn ($state) => $state ? 'Опубліковано в Telegram' : 'Не публікувалось у Telegram'),
            ])
            ->defaultSort('published_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')->label('Категорія')
                    ->relationship('category', 'title')->preload(),
                Tables\Filters\SelectFilter::make('year')->label('Рік')
                    ->options(fn () => News::query()->whereNotNull('published_at')
                        ->pluck('published_at')
                        ->map(fn ($date) => $date->format('Y'))
                        ->unique()->sortDesc()->values()
                        ->mapWithKeys(fn ($year) => [$year => $year])->all())
                    ->query(fn ($query, array $data) => filled($data['value'] ?? null)
                        ? $query->whereYear('published_at', $data['value'])
                        : $query),
                Tables\Filters\TernaryFilter::make('is_published')->label('Публікація')
                    ->trueLabel('Опубліковані')->falseLabel('Лише чернетки')->placeholder('Всі'),
            ])
            ->emptyStateHeading('Новин ще немає')
            ->emptyStateDescription('Новини зʼявляються на головній та на сторінці «Новини». Створіть першу новину - за потреби її можна зберегти чернеткою і опублікувати пізніше.')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ReplicateAction::make()
                    ->label('Дублювати')
                    ->beforeReplicaSaved(function (News $replica, News $record) {
                        $replica->title = $record->title . ' (копія)';
                        $replica->slug = UniqueSlug::copyOf(News::class, $record->slug);
                        $replica->is_published = false; // чернетка → NewsObserver не автопостить у Telegram
                        $replica->published_at = now();
                        $replica->views = 0;
                        $replica->likes = 0;
                        $replica->telegram_posted_at = null;
                    })
                    ->successRedirectUrl(fn (News $replica) => static::getUrl('edit', ['record' => $replica]))
                    ->successNotificationTitle('Копію створено чернеткою'),
                ViewOnSite::table(fn (News $record) => route('news.show', $record)),
            ])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNews::route('/'),
            'create' => Pages\CreateNews::route('/create'),
            'edit' => Pages\EditNews::route('/{record}/edit'),
        ];
    }
}
