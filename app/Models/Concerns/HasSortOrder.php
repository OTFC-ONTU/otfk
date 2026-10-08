<?php

namespace App\Models\Concerns;

/**
 * Новий запис без явного sort_order стає в кінець списку (порядок далі — перетягуванням в адмінці).
 */
trait HasSortOrder
{
    public static function bootHasSortOrder(): void
    {
        static::creating(function (self $model): void {
            if (blank($model->getAttribute('sort_order'))) {
                $model->setAttribute('sort_order', (int) static::query()->max('sort_order') + 1);
            }
        });
    }
}
