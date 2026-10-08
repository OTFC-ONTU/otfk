<?php

namespace App\Support;

use App\Models\Setting;

class BannerOverlay
{
    /** Колір brand-950 (#16223f) */
    private const RGB = '22, 34, 63';

    public static function strength(): int
    {
        return min(100, max(0, (int) (Setting::get('banner_overlay_opacity') ?? 75)));
    }

    public static function hasOverlay(): bool
    {
        return self::strength() > 0;
    }

    public static function gradientStyle(): string
    {
        $scale = self::strength() / 100;

        return sprintf(
            'background: linear-gradient(to right, rgba(%s, %s), rgba(%s, %s), rgba(%s, %s));',
            self::RGB,
            round(0.95 * $scale, 2),
            self::RGB,
            round(0.80 * $scale, 2),
            self::RGB,
            round(0.55 * $scale, 2),
        );
    }

    public static function flatStyle(): string
    {
        $scale = self::strength() / 100;

        return sprintf('background-color: rgba(%s, %s);', self::RGB, round(0.25 * $scale, 2));
    }

    /**
     * Мобільне затемнення: на вузькому екрані текст стоїть унизу слайда, тож темніє
     * низ, а верх фото (обличчя, будівля) лишається чистим. Фото-слайд без тексту
     * отримує лише легку тінь під перемикачами.
     */
    public static function mobileStyle(bool $withText): string
    {
        $scale = self::strength() / 100;
        $stops = $withText ? [0.95, 0.8, 0.35, 0.1] : [1.0, 0.55, 0.12, 0];

        return sprintf(
            'background: linear-gradient(to top, rgba(%1$s, %2$s) 0%%, rgba(%1$s, %3$s) 35%%, rgba(%1$s, %4$s) 65%%, rgba(%1$s, %5$s) 100%%);',
            self::RGB,
            ...array_map(fn (float $stop) => round($stop * $scale, 2), $stops),
        );
    }
}
