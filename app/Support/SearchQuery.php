<?php

namespace App\Support;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Http\Request;

/**
 * Пошуковий запит відвідувача: лише рядок (`q[]=…` не дає 500), обрізаний до MAX_LENGTH,
 * щоб надзвичайно довгий запит не навантажував фільтрацію, URL пагінації та розмітку.
 */
class SearchQuery
{
    public const MAX_LENGTH = 100;

    public static function from(Request $request, string $key = 'q'): string
    {
        $value = $request->query($key, '');

        return is_string($value) ? trim(mb_substr(trim($value), 0, self::MAX_LENGTH)) : '';
    }

    /**
     * Попередній відбір у MySQL (utf8mb4_unicode_ci не зважає на регістр кирилиці), щоб не вивантажувати
     * всі записи; у SQLite LIKE регістрозалежний для кирилиці, тож там лишається тільки фільтр у PHP.
     */
    public static function prefilter(Builder $query, string $column, string $term): Builder
    {
        if ($query->getConnection()->getDriverName() !== 'mysql') {
            return $query;
        }

        return $query->where($column, 'like', '%'.addcslashes($term, '\\%_').'%');
    }
}
