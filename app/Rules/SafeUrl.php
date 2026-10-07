<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Посилання для меню, плиток, банерів і оголошень: відносний шлях (`/novyny`,
 * `#yakir`, `?page=2`), назва маршруту без схеми (`news.index`) або абсолютний
 * URL зі схемою http(s)/mailto/tel. Будь-яка інша схема — `javascript:`,
 * `data:`, `vbscript:` тощо — відхиляється, бо такий href виконує код у
 * браузері відвідувача від імені сайту.
 */
class SafeUrl implements ValidationRule
{
    private const ALLOWED_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    public static function isSafe(?string $value): bool
    {
        $value = trim((string) $value);

        if ($value === '') {
            return true;
        }

        // Керівні символи та пробіли всередині схеми браузери ігнорують — прибираємо їх перед перевіркою.
        $normalized = preg_replace('/[\x00-\x20\x7f]+/u', '', $value) ?? $value;

        if (! preg_match('/^([a-z][a-z0-9+.-]*):/i', $normalized, $matches)) {
            return true;
        }

        return in_array(strtolower($matches[1]), self::ALLOWED_SCHEMES, true);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::isSafe(is_string($value) ? $value : null)) {
            $fail('Посилання має бути відносним шляхом або адресою http(s)/mailto/tel.');
        }
    }
}
