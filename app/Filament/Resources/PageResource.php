<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\FileUpload;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Actions\BulkActionGroup;
use App\Filament\Support\SafeDeleteAction;
use App\Filament\Resources\PageResource\Pages\ListPages;
use App\Filament\Resources\PageResource\Pages\CreatePage;
use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Filament\Forms\Components\HtmlRichEditor;
use App\Filament\Forms\EnglishTranslation;
use App\Filament\Resources\PageResource\Pages;
use App\Filament\Support\ViewOnSite;
use App\Models\Page;
use App\Support\UniqueSlug;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-document-text';

    protected static string | \UnitEnum | null $navigationGroup = 'Структура сайту';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Сторінки';

    protected static ?string $modelLabel = 'сторінку';

    protected static ?string $pluralModelLabel = 'Сторінки';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Контент')->schema([
                TextInput::make('title')->label('Назва сторінки')->required()->maxLength(255)->columnSpanFull(),
                TextInput::make('slug')->label('URL (slug)')->maxLength(255)
                    ->prefix(url('/') . '/')
                    // Системні адреси (Page::PROTECTED_SLUGS) використовує код сайту — не змінюються
                    ->disabled(fn (?Page $record): bool => (bool) $record?->isProtected())
                    ->dehydrated(fn (?Page $record): bool => ! $record?->isProtected())
                    ->helperText(fn (?Page $record): string => match (true) {
                        (bool) $record?->isProtected() => 'Системна адреса: на неї спирається код сайту, тому її не можна змінити.',
                        (bool) $record?->wasPublic() => 'Після зміни стара адреса автоматично перенаправлятиме на нову (і на сайті, і в пошуку).',
                        default => 'Залиште порожнім - згенерується автоматично.',
                    }),
                Select::make('parent_id')->label('Батьківський розділ')
                    ->relationship('parent', 'title', fn (Builder $query, ?Page $record) => $query
                        ->with('parent.parent.parent')
                        ->when($record?->exists, fn (Builder $q) => $q->whereNotIn('id', $record->selfAndDescendantIds()))
                        ->orderBy('title'))
                    ->getOptionLabelFromRecordUsing(fn (Page $page): string => $page->adminOptionLabel())
                    ->searchable()->preload()
                    ->helperText('Якщо обрати — сторінка стане підсторінкою і зʼявиться плиткою на сторінці розділу.'),
                Textarea::make('excerpt')->label('Короткий опис')->rows(2)->columnSpanFull()
                    ->helperText('Показується у плитці сторінки на сторінці батьківського розділу та в результатах пошуку по сайту.'),
                Toggle::make('is_heritage')
                    ->label('Урочисте оформлення (heritage)')
                    ->helperText('Увімкніть для сторінок історії, хроніки та ювілейних матеріалів — стиль «листа» на сайті.')
                    ->columnSpanFull(),
                Toggle::make('is_featured')
                    ->label('Ключова сторінка розділу')
                    ->helperText('На сторінці батьківського розділу така сторінка виноситься нагору окремою великою карткою.')
                    ->columnSpanFull(),
                HtmlRichEditor::make('body')->label('Основний текст')
                    ->fileAttachmentsDisk('public')
                    ->fileAttachmentsDirectory('pages')
                    ->fileAttachmentsVisibility('public')
                    ->helperText('Зображення можна вставляти просто в текст — кнопкою прикріплення в редакторі.')
                    ->columnSpanFull(),
                FileUpload::make('cover_image')->label('Зображення')->image()->directory('pages')->imageEditor()->imageResizeMode('contain')->imageResizeTargetWidth('1600')->imageResizeTargetHeight('1600')->columnSpanFull()
                    ->helperText('Горизонтальне фото вгорі сторінки та в її плитці. Необовʼязково.'),
            ])->columns(2),
            EnglishTranslation::section(true),
            Section::make('Налаштування')->schema([
                Toggle::make('is_published')->label('Опубліковано')->default(true)
                    ->helperText('Вимкнено — чернетка: відвідувачам сторінка не видна (404), адміну відкривається з плашкою «Чернетка».'),
                TextInput::make('section')->label('Розділ (службове поле)')->maxLength(255)
                    ->helperText('Технічна позначка з імпорту старого сайту — заповнювати не потрібно.'),
                TextInput::make('meta_title')->label('SEO-заголовок')->maxLength(255)
                    ->helperText('Заголовок вкладки браузера та в Google. Порожнє — використовується назва сторінки.'),
                Textarea::make('meta_description')->label('SEO-опис')->rows(2)->maxLength(500)->columnSpanFull()
                    ->helperText('Опис сторінки для Google. Порожнє — використовується короткий опис.'),
            ])->columns(2)->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('Назва')->searchable()->weight('bold'),
                TextColumn::make('parent.title')->label('Розділ')->badge()->placeholder('-')->sortable(),
                TextColumn::make('slug')->label('URL')->color('gray')->toggleable(),
                TextColumn::make('translation_status')->label('Переклад EN')
                    ->state(fn (Page $record) => $record->translationStatus())->badge(),
                IconColumn::make('is_published')->label('Опубл.')->boolean(),
                IconColumn::make('is_heritage')->label('Heritage')->boolean()->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_featured')->label('Ключова')->boolean()->toggleable(),
                TextColumn::make('sort_order')->label('Порядок')->numeric()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('title')
            // Порядок плиток серед сусідніх сторінок розділу — перетягуванням (краще з фільтром «Розділ»)
            ->reorderable('sort_order')
            ->filters([
                SelectFilter::make('parent_id')->label('Розділ')
                    ->relationship('parent', 'title', fn (Builder $query) => $query->with('parent.parent.parent')->whereHas('children'))
                    ->getOptionLabelFromRecordUsing(fn (Page $page): string => $page->adminOptionLabel())
                    ->searchable()->preload(),
                TernaryFilter::make('is_published')->label('Публікація')
                    ->trueLabel('Опубліковані')->falseLabel('Лише чернетки')->placeholder('Всі'),
            ])
            ->emptyStateHeading('Сторінок ще немає')
            ->emptyStateDescription('Сторінки - це постійні розділи сайту: «Історія», «Бібліотека», «Абітурієнту» тощо. Створіть першу сторінку, і вона зʼявиться на сайті за своєю адресою.')
            ->recordActions([
                EditAction::make(),
                ReplicateAction::make()
                    ->label('Дублювати')
                    ->beforeReplicaSaved(function (Page $replica, Page $record) {
                        $replica->title = $record->title . ' (копія)';
                        $replica->slug = UniqueSlug::copyOf(Page::class, $record->slug);
                        $replica->is_published = false;
                    })
                    ->successRedirectUrl(fn (Page $replica) => static::getUrl('edit', ['record' => $replica]))
                    ->successNotificationTitle('Копію створено чернеткою'),
                ViewOnSite::table(fn (Page $record) => url('/' . $record->slug)),
            ])
            ->toolbarActions([BulkActionGroup::make([SafeDeleteAction::bulk()])]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPages::route('/'),
            'create' => CreatePage::route('/create'),
            'edit' => EditPage::route('/{record}/edit'),
        ];
    }
}
