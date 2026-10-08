<?php

namespace App\Models\Concerns;

/**
 * Новий запис без явного sort_order отримує місце в списку (далі порядок — перетягуванням в адмінці):
 * у кінці (max+1) або, для стрічок «новіше вгорі» (альбоми, відео, документи, банери — раніше
 * сортувалися за датою при однаковому 0), на початку (min-1).
 */
trait HasSortOrder
{
    public static function bootHasSortOrder(): void
    {
        static::creating(function (self $model): void {
            if (blank($model->getAttribute('sort_order'))) {
                $model->setAttribute('sort_order', static::sortNewRecordsFirst()
                    ? (int) static::query()->min('sort_order') - 1
                    : (int) static::query()->max('sort_order') + 1);
            }
        });
    }

    /** Нові записи — на початку списку (стрічки «новіше вгорі»). */
    public static function sortNewRecordsFirst(): bool
    {
        return false;
    }
}
