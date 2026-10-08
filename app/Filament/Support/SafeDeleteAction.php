<?php

namespace App\Filament\Support;

use App\Models\Concerns\KeepsPublicUrls;
use App\Models\DocumentCategory;
use App\Models\MenuItem;
use App\Models\News;
use App\Models\Page;
use App\Support\PublicUrlRedirects;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/**
 * Видалення матеріалів із публічною адресою (сторінки, новини, спеціальності, підрозділи,
 * галереї, персонал): вікно заздалегідь показує наслідки (куди вестиме адреса, скільки
 * перенаправлень і посилань її стосується, куди перейдуть підсторінки) і пропонує безпечну
 * альтернативу — «Зняти з публікації». Сторінку, на яку спирається меню, розділ документів
 * або код сайту, видалити не можна. Самі перенаправлення створює модель (KeepsPublicUrls),
 * тож вони працюють і для масового видалення.
 */
class SafeDeleteAction
{
    public static function make(): DeleteAction
    {
        return DeleteAction::make()
            ->modalHeading(fn (Model $record): string => self::blockers($record) ? 'Цей матеріал не можна видалити' : 'Видалити матеріал?')
            ->modalDescription(fn (Model $record): HtmlString => self::describe($record))
            ->modalSubmitAction(fn (Action $action, Model $record) => self::blockers($record) ? false : $action)
            // Повторна перевірка на сервері: кнопку приховано, але виклик дії можна надіслати й напряму
            ->before(function (DeleteAction $action, Model $record): void {
                if ($blockers = self::blockers($record)) {
                    Notification::make()->danger()->title('Не видалено')->body(implode(' ', $blockers))->send();
                    $action->halt();
                }
            })
            ->extraModalFooterActions(fn (Model $record): array => ($record->is_published ?? false) ? [
                Action::make('unpublishInstead')
                    ->label('Зняти з публікації')
                    ->color('gray')
                    ->authorize(fn ($livewire): bool => ! method_exists($livewire, 'getResource') || $livewire->getResource()::canEdit($record))
                    ->action(function ($livewire) use ($record): void {
                        $record->update(['is_published' => false]);
                        if (method_exists($livewire, 'refreshFormData')) {
                            $livewire->refreshFormData(['is_published']);
                        }
                        Notification::make()->success()->title('Знято з публікації')
                            ->body('Матеріал лишився в адмінці, на сайті його не видно; доки його знову не опублікують, його адреса відповідатиме «сторінку не знайдено».')->send();
                    })
                    ->cancelParentActions(),
            ] : []);
    }

    /** Масове видалення: заблоковані матеріали пропускаються з повідомленням. */
    /** @param class-string<\Filament\Resources\Resource> $resource */
    public static function bulk(string $resource): BulkAction
    {
        return BulkAction::make('delete')
            ->authorize(fn (): bool => $resource::canDeleteAny())
            ->label('Видалити вибране')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Видалити вибрані матеріали?')
            ->modalDescription('Адреси опублікованих матеріалів перенаправлятимуться на їхній розділ або список; підсторінки перейдуть до розділу видаленої сторінки. Сторінки з меню, розділів документів і системні адреси буде пропущено.')
            ->action(function (Collection $records) use ($resource): void {
                $skipped = [];
                foreach ($records as $record) {
                    if (self::blockers($record) || ! $resource::canDelete($record)) {
                        $skipped[] = $record->title ?? $record->full_name ?? '#'.$record->getKey();

                        continue;
                    }
                    $record->delete();
                }
                $notification = Notification::make()->title('Видалено: '.($records->count() - count($skipped)));
                if ($skipped !== []) {
                    $notification->warning()->body('Пропущено (див. «Видалити» на сторінці матеріалу): '.implode(', ', array_slice($skipped, 0, 5)).(count($skipped) > 5 ? '…' : ''));
                } else {
                    $notification->success();
                }
                $notification->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    /** @return list<string> чому видаляти не можна */
    public static function blockers(Model $record): array
    {
        if (! $record instanceof Page) {
            return [];
        }

        $reasons = [];
        if ($record->isProtected()) {
            $reasons[] = 'На цю адресу спирається код сайту (особливий вигляд сторінки або посилання в шаблонах). Змінюйте лише текст.';
        }
        if ($menu = MenuItem::query()->where('page_id', $record->getKey())->count()) {
            $reasons[] = "Сторінка стоїть у меню ({$menu} пункт.) — спершу приберіть її в «Меню навігації».";
        }
        if (DocumentCategory::query()->where('page_id', $record->getKey())->exists()) {
            $reasons[] = 'Сторінка показується в розділі публічної інформації — спершу відв’яжіть її в «Категоріях документів».';
        }

        return $reasons;
    }

    /** @return list<string> що станеться після видалення */
    public static function consequences(Model $record): array
    {
        if (! in_array(KeepsPublicUrls::class, class_uses_recursive($record), true) || blank($record->slug)) {
            return [];
        }

        $path = $record->publicPath();
        $lines = [];
        if ($record->wasPublic()) {
            $lines[] = 'Адреса '.$path.' перенаправлятиметься на '.$record->publicFallbackPath().' — старі посилання, закладки й пошукові системи не отримають 404.';
            if ($incoming = PublicUrlRedirects::incoming($path)) {
                $lines[] = "Перенаправлень зі старого сайту на цю адресу: {$incoming} — їх буде переспрямовано туди ж.";
            }
        } else {
            $lines[] = 'Матеріал не опублікований — його адреса ще ніде не використовується.';
        }
        if ($record instanceof Page && ($children = Page::query()->where('parent_id', $record->getKey())->count())) {
            $parent = $record->parent_id ? Page::query()->find($record->parent_id) : null;
            $lines[] = "Підсторінок: {$children} — вони перейдуть ".($parent ? 'до розділу «'.$parent->title.'»' : 'на верхній рівень').'.';
        }
        if ($links = self::linkingMaterials($path)) {
            $lines[] = "На цю адресу посилаються інші матеріали ({$links}) — посилання працюватимуть через перенаправлення, але їх краще виправити.";
        }

        return $lines;
    }

    private static function describe(Model $record): HtmlString
    {
        $blockers = self::blockers($record);
        $items = $blockers ?: self::consequences($record);
        $intro = $blockers ? '' : '<p>Дію не можна скасувати. Якщо матеріал лише тимчасово не потрібен — краще «Зняти з публікації» (тоді адреса до повторної публікації відповідатиме «сторінку не знайдено», без перенаправлення).</p>';

        return new HtmlString($intro.($items ? '<ul style="margin-top:.5rem;list-style:disc;padding-left:1.25rem;text-align:left">'.implode('', array_map(fn (string $line) => '<li>'.e($line).'</li>', $items)).'</ul>' : ''));
    }

    private static function linkingMaterials(string $path): int
    {
        $like = fn ($query, string $column) => $query->where($column, 'like', '%href="'.$path.'"%')
            ->orWhere($column, 'like', '%href="'.$path.'#%')
            ->orWhere($column, 'like', '%href="'.$path.'?%');

        return Page::query()->where(fn ($query) => $like($query, 'body'))->count()
            + News::query()->where(fn ($query) => $like($query, 'body'))->count();
    }
}
