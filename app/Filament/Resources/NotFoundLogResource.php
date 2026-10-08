<?php

namespace App\Filament\Resources;

use Illuminate\Auth\Access\Response;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use App\Filament\Resources\NotFoundLogResource\Pages\ListNotFoundLogs;
use App\Filament\Resources\NotFoundLogResource\Pages;
use App\Models\LegacyRedirect;
use App\Models\NotFoundLog;
use App\Support\LegacyRedirects;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

/**
 * Журнал 404 (docs/seo-plan.md): адреси, яких немає на сайті, з кількістю
 * звернень і джерелом переходу. Звідси адміністратор створює точний редирект
 * на вибраний матеріал або позначає адресу як ігноровану. Лише admin.
 */
class NotFoundLogResource extends Resource
{
    protected static ?string $model = NotFoundLog::class;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    /** Записи створює лише обробник 404; у Filament 4 доступ перевіряється через *AuthorizationResponse(). */
    public static function getCreateAuthorizationResponse(): Response
    {
        return Response::deny();
    }

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static string | \UnitEnum | null $navigationGroup = 'SEO';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Журнал 404';

    protected static ?string $modelLabel = 'адреса 404';

    protected static ?string $pluralModelLabel = 'Журнал 404';

    public static function getNavigationBadge(): ?string
    {
        $count = NotFoundLog::where('status', NotFoundLog::NEW)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('url')->label('Адреса')->wrap()
                    ->searchable(query: fn ($query, string $search) => $query->where('path', 'like', "%{$search}%")),
                TextColumn::make('hits')->label('Звернень')->numeric()->sortable(),
                TextColumn::make('referrer')->label('Звідки перехід')->wrap()->placeholder('—')->limit(60),
                TextColumn::make('last_seen_at')->label('Останнє')->since()->sortable(),
                TextColumn::make('first_seen_at')->label('Перше')->date('d.m.Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')->label('Стан')->badge()
                    ->formatStateUsing(fn (string $state) => NotFoundLog::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        NotFoundLog::NEW => 'warning', NotFoundLog::RESOLVED => 'success', default => 'gray',
                    }),
            ])
            ->defaultSort('hits', 'desc')
            ->filters([
                SelectFilter::make('status')->label('Стан')->options(NotFoundLog::STATUSES)->default(NotFoundLog::NEW),
                Filter::make('external')->label('Лише з переходами з інших сайтів')
                    ->query(fn ($query) => $query->whereNotNull('referrer')
                        ->where('referrer', 'not like', '%://'.request()->getHost().'/%')),
            ])
            ->emptyStateHeading('Нових адрес 404 немає')
            ->emptyStateDescription('Тут з’являються старі чи помилкові адреси, за якими приходять відвідувачі. Записи без звернень понад 90 днів видаляються автоматично.')
            ->recordActions([
                self::redirectAction(),
                Action::make('ignore')->label('Ігнорувати')->icon('heroicon-o-eye-slash')->color('gray')
                    ->visible(fn (NotFoundLog $record) => $record->status !== NotFoundLog::IGNORED)
                    ->action(fn (NotFoundLog $record) => $record->update(['status' => NotFoundLog::IGNORED])),
                Action::make('reopen')->label('Повернути в нові')->icon('heroicon-o-arrow-path')->color('gray')
                    ->visible(fn (NotFoundLog $record) => $record->status !== NotFoundLog::NEW)
                    ->action(fn (NotFoundLog $record) => $record->update(['status' => NotFoundLog::NEW])),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('ignore')->label('Ігнорувати')->icon('heroicon-o-eye-slash')
                        ->action(fn (Collection $records) => NotFoundLog::whereKey($records->modelKeys())->update(['status' => NotFoundLog::IGNORED]))
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /** Точний редирект з цієї адреси на вручну вибраний матеріал (або 410 за рішенням редактора). */
    private static function redirectAction(): Action
    {
        return Action::make('redirect')->label('Створити редирект')->icon('heroicon-o-arrow-uturn-right')->color('success')
            ->visible(fn (NotFoundLog $record) => $record->status !== NotFoundLog::RESOLVED)
            ->modalDescription(fn (NotFoundLog $record) => 'Стара адреса: '.$record->url)
            ->schema([
                Select::make('action')->label('Дія')->required()->live()->default(LegacyRedirect::REDIRECT)
                    ->options([LegacyRedirect::REDIRECT => 'Постійний редирект (301)', LegacyRedirect::GONE => '410 — матеріал видалено назавжди']),
                TextInput::make('target_url')->label('Нова адреса')->maxLength(2000)
                    ->visible(fn (Get $get) => $get('action') !== LegacyRedirect::GONE)
                    ->required(fn (Get $get) => $get('action') !== LegacyRedirect::GONE)
                    ->helperText('Відносна адреса відповідного матеріалу: відкрийте його на сайті й скопіюйте шлях, напр. /novyny/nazva.'),
                TextInput::make('note')->label('Примітка')->maxLength(500),
            ])
            ->action(function (NotFoundLog $record, array $data, Action $action) {
                $gone = $data['action'] === LegacyRedirect::GONE;
                $target = $gone ? null : trim((string) $data['target_url']);

                $error = $gone ? null : (LegacyRedirect::targetError($target)
                    ?? (LegacyRedirects::resolves($target) ? null : 'За новою адресою немає опублікованої сторінки чи файлу.'));
                if (! $error) {
                    try {
                        LegacyRedirect::create([
                            'source_path' => $record->path,
                            'source_query' => $record->query,
                            'action' => $data['action'],
                            'target_url' => $target,
                            'status_code' => $gone ? 410 : 301,
                            'note' => filled($data['note'] ?? null) ? $data['note'] : 'З журналу 404',
                        ]);
                    } catch (ValidationException $e) {
                        $error = collect($e->errors())->flatten()->implode(' ');
                    } catch (UniqueConstraintViolationException) {
                        $error = 'Для цієї адреси вже є запис у карті редиректів.';
                    }
                }

                if ($error) {
                    Notification::make()->title('Редирект не створено')->body($error)->danger()->send();
                    $action->halt();
                }

                $record->update(['status' => NotFoundLog::RESOLVED]);
                Notification::make()->title($gone ? 'Адресу позначено як видалену (410)' : 'Редирект створено')->success()->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNotFoundLogs::route('/'),
        ];
    }
}
