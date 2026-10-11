<?php

namespace App\Services\AutoTranslation;

/**
 * Приблизна вартість запиту за довідковими цінами config('auto_translate.pricing').
 * Ключ — префікс ID моделі (найдовший збіг): claude-opus-4-5-20251101 → claude-opus-4-5.
 */
final class Pricing
{
    /** @return array{0: float, 1: float}|null [вхід, вихід] USD за 1 млн токенів */
    public static function rates(?string $model): ?array
    {
        if (blank($model)) {
            return null;
        }

        $prices = (array) config('auto_translate.pricing', []);
        uksort($prices, fn ($a, $b) => strlen($b) <=> strlen($a));
        foreach ($prices as $prefix => $rates) {
            if ($model === $prefix || str_starts_with($model, $prefix.'-')) {
                return [(float) $rates[0], (float) $rates[1]];
            }
        }

        return null;
    }

    public static function cost(?string $model, int $input, int $output, int $cacheRead = 0, int $cacheWrite = 0): ?float
    {
        $rates = self::rates($model);
        if ($rates === null) {
            return null;
        }

        [$in, $out] = $rates;

        return round(($input * $in + $output * $out + $cacheRead * $in * 0.1 + $cacheWrite * $in * 1.25) / 1_000_000, 6);
    }

    public static function label(?string $model): ?string
    {
        $rates = self::rates($model);

        return $rates === null ? null : sprintf('$%s / $%s за 1 млн токенів (вхід / вихід)', self::number($rates[0]), self::number($rates[1]));
    }

    private static function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
