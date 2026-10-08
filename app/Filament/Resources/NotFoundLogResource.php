<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NotFoundLogResource\Pages;
use App\Models\LegacyRedirect;
use App\Models\NotFoundLog;
use App\Support\LegacyRedirects;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
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

    public static function canCreate(): bool
    {
        return false;
    }

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationGroup = 'SEO';

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
                Tables\Columns\TextColumn::make('url')->label('Адреса')->wrap()
                    ->searchable(query: fn ($query, string $search) => $query->where('path', 'like', "%{$search}%")),
                Tables\Columns\TextColumn::make('hits')->label('Звернень')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('referrer')->label('Звідки перехід')->wrap()->placeholder('—')->limit(60),
                Tables\Columns\TextColumn::make('last_seen_at')->label('Останнє')->since()->sortable(),
                Tables\Columns\TextColumn::make('first_seen_at')->label('Перше')->date('d.m.Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('status')->label('Стан')->badge()
                    ->formatStateUsing(fn (string $state) => NotFoundLog::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        NotFoundLog::NEW => 'warning', NotFoundLog::RESOLVED => 'success', default => 'gray',
                    }),
            ])
            ->defaultSort('hits', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('Стан')->options(NotFoundLog::STATUSES)->default(NotFoundLog::NEW),
                Tables\Filters\Filter::make('external')->label('Лише з переходами з інших сайтів')
                    ->query(fn ($query) => $query->whereNotNull('referrer')
                        ->where('referrer', 'not like', '%://'.request()->getHost().'/%')),
            ])
            ->emptyStateHeading('Нових адрес 404 немає')
            ->emptyStateDescription('Тут з’являються старі чи помилкові адреси, за якими приходять відвідувачі. Записи без звернень понад 90 днів видаляються автоматично.')
            ->actions([
                self::redirectAction(),
                Action::make('ignore')->label('Ігнорувати')->icon('heroicon-o-eye-slash')->color('gray')
                    ->visible(fn (NotFoundLog $record) => $record->status !== NotFoundLog::IGNORED)
                    ->action(fn (NotFoundLog $record) => $record->update(['status' => NotFoundLog::IGNORED])),
                Action::make('reopen')->label('Повернути в нові')->icon('heroicon-o-arrow-path')->color('gray')
                    ->visible(fn (NotFoundLog $record) => $record->status !== NotFoundLog::NEW)
                    ->action(fn (NotFoundLog $record) => $record->update(['status' => NotFoundLog::NEW])),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('ignore')->label('Ігнорувати')->icon('heroicon-o-eye-slash')
                        ->action(fn (Collection $records) => NotFoundLog::whereKey($records->modelKeys())->update(['status' => NotFoundLog::IGNORED]))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    /** Точний редирект з цієї адреси на вручну вибраний матеріал (або 410 за рішенням редактора). */
    private static function redirectAction(): Action
    {
        return Action::make('redirect')->label('Створити редирект')->icon('heroicon-o-arrow-uturn-right')->color('success')
            ->visible(fn (NotFoundLog $record) => $record->status !== NotFoundLog::RESOLVED)
            ->modalDescription(fn (NotFoundLog $record) => 'Стара адреса: '.$record->url)
            ->form([
                Forms\Components\Select::make('action')->label('Дія')->required()->live()->default(LegacyRedirect::REDIRECT)
                    ->options([LegacyRedirect::REDIRECT => 'Постійний редирект (301)', LegacyRedirect::GONE => '410 — матеріал видалено назавжди']),
                Forms\Components\TextInput::make('target_url')->label('Нова адреса')->maxLength(2000)
                    ->visible(fn (Forms\Get $get) => $get('action') !== LegacyRedirect::GONE)
                    ->required(fn (Forms\Get $get) => $get('action') !== LegacyRedirect::GONE)
                    ->helperText('Відносна адреса відповідного матеріалу: відкрийте його на сайті й скопіюйте шлях, напр. /novyny/nazva.'),
                Forms\Components\TextInput::make('note')->label('Примітка')->maxLength(500),
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
            'index' => Pages\ListNotFoundLogs::route('/'),
        ];
    }
}
