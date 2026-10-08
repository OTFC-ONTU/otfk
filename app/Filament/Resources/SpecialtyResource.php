<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\SpecialtyResource\Pages\ListSpecialties;
use App\Filament\Resources\SpecialtyResource\Pages\CreateSpecialty;
use App\Filament\Resources\SpecialtyResource\Pages\EditSpecialty;
use App\Filament\Forms\Components\HtmlRichEditor;
use App\Filament\Forms\EnglishTranslation;
use App\Filament\Resources\SpecialtyResource\Pages;
use App\Filament\Support\ViewOnSite;
use App\Models\Specialty;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SpecialtyResource extends Resource
{
    protected static ?string $model = Specialty::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-academic-cap';

    protected static string | \UnitEnum | null $navigationGroup = 'Абітурієнту';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Спеціальності';

    protected static ?string $modelLabel = 'спеціальність';

    protected static ?string $pluralModelLabel = 'Спеціальності';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Основне')->schema([
                TextInput::make('title')->label('Назва спеціальності')->required()->maxLength(255)->columnSpanFull(),
                TextInput::make('code')->label('Код')->maxLength(255)->placeholder('напр., 121')
                    ->helperText('Офіційний код спеціальності — бейдж на картці.'),
                TextInput::make('slug')->label('URL (slug)')->maxLength(255)
                    ->prefix(url('/spetsialnosti') . '/')
                    ->helperText('Залиште порожнім - згенерується автоматично.'),
                Textarea::make('short_description')->label('Короткий опис')->rows(2)->columnSpanFull()
                    ->helperText('1-2 речення в картці спеціальності у списку та в результаті квізу.'),
                HtmlRichEditor::make('description')->label('Повний опис')->columnSpanFull()
                    ->helperText('Основний текст на сторінці спеціальності.'),
                FileUpload::make('cover_image')->label('Зображення')->image()->directory('specialties')->imageEditor()->imageResizeMode('contain')->imageResizeTargetWidth('1600')->imageResizeTargetHeight('1600')->columnSpanFull()
                    ->helperText('Горизонтальне фото в картці та вгорі сторінки спеціальності.'),
            ])->columns(2),
            Section::make('Деталі навчання')->schema([
                TextInput::make('degree')->label('Освітній ступінь')->maxLength(255)->placeholder('Фаховий молодший бакалавр'),
                TextInput::make('study_form')->label('Форма навчання')->maxLength(255)->placeholder('Денна / Заочна'),
                TextInput::make('duration')->label('Термін навчання')->maxLength(255)->placeholder('3 роки 10 місяців'),
                Toggle::make('is_published')->label('Опубліковано')->default(true),
            ])->columns(2),
            EnglishTranslation::academicSection(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('translation_status')->label('Переклад EN')
                    ->state(fn (Specialty $record) => $record->translationStatus())->badge(),
                ImageColumn::make('cover_image')->label('')->square(),
                TextColumn::make('title')->label('Назва')->searchable()->weight('bold')->wrap(),
                TextColumn::make('code')->label('Код')->badge()->toggleable(),
                TextColumn::make('degree')->label('Ступінь')->toggleable(),
                TextColumn::make('programs_count')->label('Програм')->counts('programs')->badge(),
                IconColumn::make('is_published')->label('Опубл.')->boolean(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->emptyStateHeading('Спеціальностей ще немає')
            ->emptyStateDescription('Спеціальності показуються на сторінці «Спеціальності» та в квізі для вступників. Додайте першу спеціальність з кодом і описом.')
            ->recordActions([
                EditAction::make(),
                ViewOnSite::table(fn (Specialty $record) => route('specialties.show', $record)),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSpecialties::route('/'),
            'create' => CreateSpecialty::route('/create'),
            'edit' => EditSpecialty::route('/{record}/edit'),
        ];
    }
}
