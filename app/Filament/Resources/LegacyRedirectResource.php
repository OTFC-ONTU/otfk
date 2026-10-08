<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\LegacyRedirectResource\Pages\ListLegacyRedirects;
use App\Filament\Resources\LegacyRedirectResource\Pages\CreateLegacyRedirect;
use App\Filament\Resources\LegacyRedirectResource\Pages\EditLegacyRedirect;
use App\Filament\Resources\LegacyRedirectResource\Pages;
use App\Models\LegacyRedirect;
use App\Support\LegacyRedirects;
use Closure;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Карта старих адрес (docs/seo-plan.md): 301/308 на сторінку нового сайту або
 * 410 для свідомо видаленого. Масове наповнення — командою otfk:legacy-redirects
 * з CSV; тут — пошук, точкові правки й перевірка призначення. Лише admin.
 */
class LegacyRedirectResource extends Resource
{
    protected static ?string $model = LegacyRedirect::class;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-arrow-uturn-right';

    protected static string | \UnitEnum | null $navigationGroup = 'SEO';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Редиректи старих адрес';

    protected static ?string $modelLabel = 'редирект';

    protected static ?string $pluralModelLabel = 'Редиректи старих адрес';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('source')->label('Стара адреса')->required()->maxLength(2000)
                ->afterStateHydrated(fn (TextInput $component, ?LegacyRedirect $record) => $component->state($record?->source))
                ->helperText('Шлях старого сайту: /uploads/2019/foto.jpg або повна адреса https://otfk.od.ua/... Параметри старої CMS (?p=12) — після «?», мітки utm_* відкидаються.')
                ->rule(fn (Get $get, ?LegacyRedirect $record) => self::sourceRule($get, $record)),
            Select::make('action')->label('Дія')->required()->live()->default(LegacyRedirect::REDIRECT)
                ->options([LegacyRedirect::REDIRECT => 'Постійний редирект', LegacyRedirect::GONE => '410 — матеріал видалено назавжди'])
                ->helperText('410 — лише для свідомо видаленого матеріалу без заміни (рішення редактора).'),
            TextInput::make('target_url')->label('Нова адреса')->maxLength(2000)
                ->visible(fn (Get $get) => $get('action') !== LegacyRedirect::GONE)
                ->required(fn (Get $get) => $get('action') !== LegacyRedirect::GONE)
                ->helperText('Відносна адреса цього сайту, напр. /novyny/nazva або /storage/mirror/otfk.od.ua/uploads/doc.pdf. Головну для всіх старих сторінок не вказуйте.')
                ->rule(fn (Get $get, ?LegacyRedirect $record) => function (string $attribute, mixed $value, Closure $fail) use ($get, $record) {
                    if ($error = LegacyRedirect::targetError((string) $value)) {
                        $fail($error);
                    } elseif (! LegacyRedirects::resolves((string) $value)) {
                        $fail('За новою адресою немає опублікованої сторінки чи файлу.');
                    } elseif ($error = self::problemsFor($get, $record)['target_url'] ?? null) {
                        $fail($error);
                    }
                }),
            Select::make('status_code')->label('Код')->default(301)
                ->options([301 => '301 Moved Permanently', 308 => '308 Permanent Redirect'])
                ->visible(fn (Get $get) => $get('action') !== LegacyRedirect::GONE)
                ->required(fn (Get $get) => $get('action') !== LegacyRedirect::GONE),
            Toggle::make('is_active')->label('Активний')->default(true),
            TextInput::make('note')->label('Примітка')->maxLength(500)
                ->helperText('Джерело запису або підстава видалення.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('source')->label('Стара адреса')->wrap()
                    ->searchable(query: fn ($query, string $search) => $query->where('source_path', 'like', "%{$search}%")),
                TextColumn::make('action')->label('Дія')->badge()
                    ->formatStateUsing(fn (LegacyRedirect $record) => $record->action === LegacyRedirect::GONE ? '410' : (string) $record->status_code)
                    ->color(fn (string $state) => $state === LegacyRedirect::GONE ? 'danger' : 'success'),
                TextColumn::make('target_url')->label('Нова адреса')->wrap()->placeholder('—')->searchable(),
                TextColumn::make('hits')->label('Переходів')->numeric()->sortable(),
                TextColumn::make('last_hit_at')->label('Останній перехід')->since()->sortable()->placeholder('—'),
                IconColumn::make('is_active')->label('Активний')->boolean(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                SelectFilter::make('action')->label('Дія')
                    ->options([LegacyRedirect::REDIRECT => 'Редирект', LegacyRedirect::GONE => '410']),
                TernaryFilter::make('is_active')->label('Активний'),
            ])
            ->emptyStateHeading('Карта старих адрес порожня')
            ->emptyStateDescription('Записи додаються командою otfk:legacy-redirects з CSV або з журналу 404 («Створити редирект»).')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /** Поле форми «Стара адреса» → source_path/source_query; для 410 — код і порожнє призначення. */
    public static function mutate(array $data): array
    {
        [$data['source_path'], $data['source_query']] = LegacyRedirects::parseSource((string) ($data['source'] ?? '')) ?? ['', null];
        unset($data['source']);

        if (($data['action'] ?? null) === LegacyRedirect::GONE) {
            $data['status_code'] = 410;
            $data['target_url'] = null;
        }

        return $data;
    }

    /** Перевірка старої адреси разом із дією й призначенням (цикли, ланцюжки, жива сторінка). */
    private static function sourceRule(Get $get, ?LegacyRedirect $record): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($get, $record) {
            $parsed = LegacyRedirects::parseSource((string) $value);
            if (! $parsed) {
                $fail('Вкажіть шлях, що починається з «/», або адресу на основному домені.');

                return;
            }
            [$path, $query] = $parsed;

            $duplicate = LegacyRedirect::where('source_hash', LegacyRedirects::hash($path, $query))
                ->when($record, fn ($q) => $q->whereKeyNot($record->getKey()))->exists();
            if ($duplicate) {
                $fail('Для цієї старої адреси вже є запис.');

                return;
            }
            if ($query === null && LegacyRedirects::resolves($path)) {
                $fail('Ця адреса зараз відкривається на новому сайті — редирект не спрацює й не потрібен.');

                return;
            }

            $errors = self::problemsFor($get, $record);
            unset($errors['target_url']); // повідомлення про призначення показує його власне поле
            foreach ($errors as $message) {
                $fail($message);
            }
        };
    }

    /**
     * Помилки моделі для поточного стану форми (порожньо для неактивного запису).
     *
     * @return array<string, string>
     */
    private static function problemsFor(Get $get, ?LegacyRedirect $record): array
    {
        $parsed = LegacyRedirects::parseSource((string) $get('source'));
        if (! $parsed || ! $get('is_active')) {
            return [];
        }
        $gone = $get('action') === LegacyRedirect::GONE;

        return LegacyRedirect::problems($parsed[0], $parsed[1], (string) $get('action'),
            $gone ? null : (string) $get('target_url'), $gone ? 410 : (int) $get('status_code'), $record?->getKey());
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLegacyRedirects::route('/'),
            'create' => CreateLegacyRedirect::route('/create'),
            'edit' => EditLegacyRedirect::route('/{record}/edit'),
        ];
    }
}
