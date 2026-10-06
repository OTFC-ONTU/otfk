<?php

namespace App\Support;

/**
 * Унікальний slug для копії запису (дія «Дублювати» в адмінці):
 * суфікс «-kopiya», далі «-kopiya-2», «-kopiya-3»… поки не звільниться.
 */
class UniqueSlug
{
    /**
     * @param class-string<\Illuminate\Database\Eloquent\Model> $modelClass
     */
    public static function copyOf(string $modelClass, string $baseSlug): string
    {
        $slug = $baseSlug . '-kopiya';
        $i = 2;

        while ($modelClass::query()->where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-kopiya-' . $i++;
        }

        return $slug;
    }
}
