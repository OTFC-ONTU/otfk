<?php

namespace App\Casts;

use App\Support\HtmlSanitizer;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Каст для HTML-полів редактора: під час запису значення проходить
 * App\Support\HtmlSanitizer, читання повертає збережений рядок як є.
 */
class SafeHtml implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : (string) $value;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : HtmlSanitizer::clean((string) $value);
    }
}
