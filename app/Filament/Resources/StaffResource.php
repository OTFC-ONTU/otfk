<?php

namespace App\Filament\Resources;

use App\Filament\Forms\EnglishTranslation;
use App\Filament\Resources\StaffResource\Pages;
use App\Filament\Support\ViewOnSite;
use App\Models\Staff;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class StaffResource extends Resource
{
    protected static ?string $recordRouteKeyName = 'id';

    protected static ?string $model = Staff::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Структура та персонал';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Персонал';

    protected static ?string $modelLabel = 'працівника';

    protected static ?string $pluralModelLabel = 'Персонал';

    /**
     * Адреси админки — за ID, хоча публічний ключ моделі — slug: Filament передає модель у route(),
     * і Laravel підставив би getRouteKey() (slug), який потім шукається як id → 404.
     */
    public static function getUrl(string $name = 'index', array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?\Illuminate\Database\Eloquent\Model $tenant = null): string
    {
        if (($parameters['record'] ?? null) instanceof Staff) {
            $parameters['record'] = $parameters['record']->getKey();
        }

        return parent::getUrl($name, $parameters, $isAbsolute, $panel, $tenant);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\FileUpload::make('photo')->label('Фото')->image()->avatar()->directory('staff')->imageEditor()->imageResizeMode('contain')->imageResizeTargetWidth('600')->imageResizeTargetHeight('600'),
            Forms\Components\TextInput::make('full_name')->label('ПІБ')->required()->maxLength(255)->columnSpanFull(),
            Forms\Components\TextInput::make('slug')->label('Слаг (URL персональної сторінки)')->maxLength(255)->unique(ignoreRecord: true)
                ->prefix(url('/personal') . '/')
                ->helperText('Порожній — згенерується з ПІБ.')->columnSpanFull(),
            Forms\Components\TextInput::make('position')->label('Посада')->maxLength(255)->columnSpanFull()
                ->helperText('Показується під ПІБ. На сторінці «Адміністрація» за посадою людей групують у блоки.'),
            Forms\Components\Select::make('category')->label('Категорія')->required()->default('teacher')
                ->options(Staff::CATEGORIES)
                ->helperText('«Адміністрація» — людина показується на сторінці «Адміністрація»; «Викладач» — на сторінці свого підрозділу.'),
            Forms\Components\Select::make('department_id')->label('Підрозділ')
                ->relationship('department', 'title')->searchable()->preload(),
            Forms\Components\Select::make('profile_page_id')->label('Сторінка: результати професійної діяльності')
                ->relationship('profilePage', 'title')->searchable()->preload(),
            Forms\Components\Select::make('qualification_page_id')->label('Сторінка: підвищення кваліфікації')
                ->relationship('qualificationPage', 'title')->searchable()->preload(),
            Forms\Components\TextInput::make('academic_degree')->label('Науковий ступінь / звання')->maxLength(255),
            Forms\Components\TextInput::make('email')->label('Email')->email()->maxLength(255)
                ->helperText('Показується на персональній сторінці працівника. Необовʼязково.'),
            Forms\Components\TextInput::make('phone')->label('Телефон')->maxLength(255),
            Forms\Components\Textarea::make('bio')->label('Біографія')->rows(3)->columnSpanFull()
                ->helperText('Кілька речень на персональній сторінці працівника. Необовʼязково.'),
            Forms\Components\TextInput::make('sort_order')->label('Порядок')->numeric()->default(0)
                ->helperText('Простіше змінити перетягуванням рядків у списку (кнопка «Змінити порядок»).'),
            Forms\Components\Toggle::make('is_published')->label('Опубліковано')->default(true),
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
                Tables\Columns\TextColumn::make('translation_status')->label('Переклад EN')
                    ->state(fn (Staff $record) => $record->translationStatus())->badge(),
                Tables\Columns\ImageColumn::make('photo')->label('')->circular(),
                Tables\Columns\TextColumn::make('full_name')->label('ПІБ')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('position')->label('Посада')->wrap()->toggleable(),
                Tables\Columns\TextColumn::make('category')->label('Категорія')->badge()
                    ->formatStateUsing(fn ($state) => Staff::CATEGORIES[$state] ?? $state),
                Tables\Columns\TextColumn::make('department.title')->label('Підрозділ')->placeholder('-')->toggleable(),
                Tables\Columns\ToggleColumn::make('is_published')->label('Опубл.'),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                Tables\Filters\SelectFilter::make('department_id')->label('Підрозділ')
                    ->relationship('department', 'title')->searchable()->preload(),
                Tables\Filters\SelectFilter::make('category')->label('Категорія')
                    ->options(Staff::CATEGORIES),
            ])
            ->emptyStateHeading('Працівників ще немає')
            ->emptyStateDescription('Персонал показується на сторінках «Адміністрація» та в підрозділах. Додайте працівника з фото, посадою і підрозділом.')
            ->actions([
                Tables\Actions\EditAction::make(),
                ViewOnSite::table(fn (Staff $record) => route('staff.show', $record)),
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
            'index' => Pages\ListStaff::route('/'),
            'create' => Pages\CreateStaff::route('/create'),
            'edit' => Pages\EditStaff::route('/{record}/edit'),
        ];
    }
}
