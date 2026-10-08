<?php

namespace App\Filament\Resources;

use Illuminate\Database\Eloquent\Model;
use Filament\Schemas\Schema;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use App\Filament\Support\SafeDeleteAction;
use App\Filament\Resources\StaffResource\Pages\ListStaff;
use App\Filament\Resources\StaffResource\Pages\CreateStaff;
use App\Filament\Resources\StaffResource\Pages\EditStaff;
use App\Filament\Forms\EnglishTranslation;
use App\Filament\Resources\StaffResource\Pages;
use App\Filament\Support\ViewOnSite;
use App\Models\Staff;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class StaffResource extends Resource
{
    protected static ?string $recordRouteKeyName = 'id';

    protected static ?string $model = Staff::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-users';

    protected static string | \UnitEnum | null $navigationGroup = 'Структура та персонал';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Персонал';

    protected static ?string $modelLabel = 'працівника';

    protected static ?string $pluralModelLabel = 'Персонал';

    /**
     * Адреси админки — за ID, хоча публічний ключ моделі — slug: Filament передає модель у route(),
     * і Laravel підставив би getRouteKey() (slug), який потім шукається як id → 404.
     */
    public static function getUrl(?string $name = null, array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?Model $tenant = null, bool $shouldGuessMissingParameters = false, ?string $configuration = null): string
    {
        if (($parameters['record'] ?? null) instanceof Staff) {
            $parameters['record'] = $parameters['record']->getKey();
        }

        return parent::getUrl($name, $parameters, $isAbsolute, $panel, $tenant, $shouldGuessMissingParameters, $configuration);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            FileUpload::make('photo')->label('Фото')->image()->avatar()->directory('staff')->imageEditor()->imageResizeMode('contain')->imageResizeTargetWidth('600')->imageResizeTargetHeight('600'),
            TextInput::make('full_name')->label('ПІБ')->required()->maxLength(255)->columnSpanFull(),
            TextInput::make('slug')->label('Слаг (URL персональної сторінки)')->maxLength(255)->unique(ignoreRecord: true)
                ->prefix(url('/personal') . '/')
                ->helperText(fn ($record): string => $record?->wasPublic() ? 'Після зміни стара адреса автоматично перенаправлятиме на нову (і на сайті, і в пошуку).' : 'Порожній — згенерується з ПІБ.')->columnSpanFull(),
            TextInput::make('position')->label('Посада')->maxLength(255)->columnSpanFull()
                ->helperText('Показується під ПІБ. На сторінці «Адміністрація» за посадою людей групують у блоки.'),
            Select::make('category')->label('Категорія')->required()->default('teacher')
                ->options(Staff::CATEGORIES)
                ->helperText('«Адміністрація» — людина показується на сторінці «Адміністрація»; «Викладач» — на сторінці свого підрозділу.'),
            Select::make('department_id')->label('Підрозділ')
                ->relationship('department', 'title')->searchable()->preload(),
            Select::make('profile_page_id')->label('Сторінка: результати професійної діяльності')
                ->relationship('profilePage', 'title')->searchable()->preload(),
            Select::make('qualification_page_id')->label('Сторінка: підвищення кваліфікації')
                ->relationship('qualificationPage', 'title')->searchable()->preload(),
            TextInput::make('academic_degree')->label('Науковий ступінь / звання')->maxLength(255),
            TextInput::make('email')->label('Email')->email()->maxLength(255)
                ->helperText('Показується на персональній сторінці працівника. Необовʼязково.'),
            TextInput::make('phone')->label('Телефон')->maxLength(255),
            Textarea::make('bio')->label('Біографія')->rows(3)->columnSpanFull()
                ->helperText('Кілька речень на персональній сторінці працівника. Необовʼязково.'),
            Toggle::make('is_published')->label('Опубліковано')->default(true),
            EnglishTranslation::section(contentFields: [
                'position' => ['label' => 'Посада англійською'],
                'academic_degree' => ['label' => 'Науковий ступінь / звання англійською'],
                'bio' => ['label' => 'Біографія англійською', 'rows' => 3],
            ], primaryField: 'full_name', primaryLabel: 'ПІБ латиницею'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('translation_status')->label('Переклад EN')
                    ->state(fn (Staff $record) => $record->translationStatus())->badge(),
                ImageColumn::make('photo')->label('')->circular(),
                TextColumn::make('full_name')->label('ПІБ')->searchable()->weight('bold'),
                TextColumn::make('position')->label('Посада')->wrap()->toggleable(),
                TextColumn::make('category')->label('Категорія')->badge()
                    ->formatStateUsing(fn ($state) => Staff::CATEGORIES[$state] ?? $state),
                TextColumn::make('department.title')->label('Підрозділ')->placeholder('-')->toggleable(),
                ToggleColumn::make('is_published')->label('Опубл.'),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                SelectFilter::make('department_id')->label('Підрозділ')
                    ->relationship('department', 'title')->searchable()->preload(),
                SelectFilter::make('category')->label('Категорія')
                    ->options(Staff::CATEGORIES),
            ])
            ->emptyStateHeading('Працівників ще немає')
            ->emptyStateDescription('Персонал показується на сторінках «Адміністрація» та в підрозділах. Додайте працівника з фото, посадою і підрозділом.')
            ->recordActions([
                EditAction::make(),
                ViewOnSite::table(fn (Staff $record) => route('staff.show', $record)),
            ])
            ->toolbarActions([BulkActionGroup::make([SafeDeleteAction::bulk(static::class)])]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStaff::route('/'),
            'create' => CreateStaff::route('/create'),
            'edit' => EditStaff::route('/{record}/edit'),
        ];
    }
}
