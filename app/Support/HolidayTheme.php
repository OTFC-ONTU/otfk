<?php

namespace App\Support;

use App\Models\Setting;
use Carbon\CarbonImmutable;

/**
 * Довідник святкових тем сайту. Тема обирається в адмінці
 * («Налаштування → Святкова тема»; ключі settings `holiday_theme` і
 * необовʼязковий `holiday_theme_until` — дата Y-m-d, після якої тема
 * вимикається сама за київським часом).
 *
 * Активна тема в публічному layout: перефарбовує темні смуги шапки й
 * підвалу (CSS-змінні --hd-*), вішає під шапкою SVG-гірлянду
 * (<x-holiday.garland>), ставить значок біля логотипа (<x-holiday.badge>),
 * показує вітання над підвалом і один раз за сесію запускає легкий
 * canvas-«снігопад» частинок (resources/js/holiday.js; вимкнено при
 * prefers-reduced-motion). Порожнє/невідоме значення — звичайний вигляд.
 */
class HolidayTheme
{
    /**
     * Конфігурація тем (у хронологічному порядку року):
     *  - label     — назва в адмінці;  season — підказка, коли вмикати;
     *  - top/nav   — фон верхньої смуги/підвалу та навігації (null — фірмові кольори);
     *  - accent    — акцентний колір (рамка підвалу, вітання, підсвітка меню);
     *  - garland   — lights (гірлянда-лампочки), bunting (прапорці) або embroidery (вишитий орнамент);
     *  - particles — snow, leaves, petals, confetti, code, sparks;
     *  - badge     — значок біля логотипа (resources/views/components/holiday/badge.blade.php);
     *  - greeting  — вітання (ключ словника layout.holiday.*).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            'new_year' => [
                'label' => 'Новий рік і Різдво',
                'season' => 'грудень – січень',
                'top' => '#0b2e22', 'nav' => '#12442f', 'accent' => '#e8c26a',
                'garland' => ['type' => 'lights', 'colors' => ['#ef4444', '#f5c542', '#34d399', '#60a5fa']],
                'particles' => ['type' => 'snow', 'colors' => ['#9cc0e6', '#c7dcf2', '#7aa7d8']],
                'badge' => 'snowflake',
            ],
            'easter' => [
                'label' => 'Великдень',
                'season' => 'весна',
                'top' => '#2a2350', 'nav' => '#3b3270', 'accent' => '#f9c8dc',
                'garland' => ['type' => 'bunting', 'colors' => ['#f9a8d4', '#fde68a', '#a7f3d0', '#bfdbfe', '#ddd6fe']],
                'particles' => ['type' => 'petals', 'colors' => ['#f9a8d4', '#fbcfe8', '#fde68a']],
                'badge' => 'egg',
            ],
            'vyshyvanka' => [
                'label' => 'День вишиванки',
                'season' => 'третій четвер травня',
                'top' => '#3d0b0b', 'nav' => '#8b1c1c', 'accent' => '#f3e3c3',
                'garland' => ['type' => 'embroidery', 'colors' => ['#b91c1c', '#1c1917', '#f3e3c3']],
                'particles' => ['type' => 'petals', 'colors' => ['#dc2626', '#b91c1c', '#f3e3c3']],
                'badge' => 'ornament',
            ],
            'independence' => [
                'label' => 'День Незалежності України',
                'season' => '24 серпня',
                'top' => '#003a7a', 'nav' => '#0057b7', 'accent' => '#ffd500',
                'garland' => ['type' => 'bunting', 'colors' => ['#0057b7', '#ffd500']],
                'particles' => ['type' => 'confetti', 'colors' => ['#0057b7', '#ffd500', '#3b82f6', '#facc15']],
                'badge' => 'heart',
            ],
            'knowledge' => [
                'label' => 'День знань',
                'season' => '1 вересня',
                'top' => null, 'nav' => null, 'accent' => '#e8aa35',
                'garland' => ['type' => 'bunting', 'colors' => ['#ef4444', '#f59e0b', '#22c55e', '#3b82f6', '#a855f7']],
                'particles' => ['type' => 'confetti', 'colors' => ['#ef4444', '#f59e0b', '#22c55e', '#3b82f6', '#a855f7']],
                'badge' => 'bell',
            ],
            'programmer' => [
                'label' => 'День програміста',
                'season' => '13 вересня',
                'top' => '#050a14', 'nav' => '#0f172a', 'accent' => '#4ade80',
                'garland' => ['type' => 'lights', 'colors' => ['#4ade80', '#22d3ee']],
                'particles' => ['type' => 'code', 'colors' => ['#16a34a', '#0891b2']],
                'badge' => 'code',
            ],
            'teachers' => [
                'label' => 'День працівників освіти',
                'season' => 'перша неділя жовтня',
                'top' => '#3a1d0a', 'nav' => '#6b3412', 'accent' => '#f6b73c',
                'garland' => ['type' => 'bunting', 'colors' => ['#b45309', '#f59e0b', '#dc2626', '#ca8a04']],
                'particles' => ['type' => 'leaves', 'colors' => ['#d97706', '#b45309', '#dc2626', '#ca8a04']],
                'badge' => 'leaf',
            ],
            'food' => [
                'label' => 'День працівників харчової промисловості',
                'season' => 'третя неділя жовтня',
                'top' => '#3f1606', 'nav' => '#7c2d12', 'accent' => '#fbbf24',
                'garland' => ['type' => 'bunting', 'colors' => ['#ea580c', '#fbbf24', '#65a30d', '#fef3c7']],
                'particles' => ['type' => 'petals', 'colors' => ['#f59e0b', '#fbbf24', '#d97706']],
                'badge' => 'wheat',
            ],
            'halloween' => [
                'label' => 'Гелловін',
                'season' => '31 жовтня',
                'top' => '#14061f', 'nav' => '#2e1065', 'accent' => '#fb923c',
                'garland' => ['type' => 'lights', 'colors' => ['#f97316', '#a855f7']],
                'particles' => ['type' => 'leaves', 'colors' => ['#ea580c', '#7c2d12', '#a855f7']],
                'badge' => 'pumpkin',
            ],
            'energy' => [
                'label' => 'День енергетика',
                'season' => '22 грудня',
                'top' => '#111827', 'nav' => '#1e293b', 'accent' => '#facc15',
                'garland' => ['type' => 'lights', 'colors' => ['#fde68a', '#fcd34d']],
                'particles' => ['type' => 'sparks', 'colors' => ['#facc15', '#f59e0b']],
                'badge' => 'bolt',
            ],
        ];
    }

    /** Конфігурація теми за ключем або null для звичайного вигляду. */
    public static function config(?string $key): ?array
    {
        return $key ? (static::all()[$key] ?? null) : null;
    }

    /** Ключ активної теми з урахуванням дати автовимкнення або null. */
    public static function active(): ?string
    {
        $key = trim((string) Setting::get('holiday_theme'));

        if (static::config($key) === null || static::expired(Setting::get('holiday_theme_until'))) {
            return null;
        }

        return $key;
    }

    /** Чи минула дата автовимкнення (тема діє до кінця цього дня за Києвом). */
    public static function expired(?string $until): bool
    {
        $until = trim((string) $until);
        if ($until === '') {
            return false;
        }

        try {
            $date = CarbonImmutable::createFromFormat('Y-m-d', substr($until, 0, 10), 'Europe/Kyiv');
        } catch (\Throwable) {
            return false;
        }

        return $date->endOfDay()->isPast();
    }

    /** CSS-змінні теми для <body>. */
    public static function style(array $theme): string
    {
        return collect(['--hd-top' => $theme['top'], '--hd-nav' => $theme['nav'], '--hd-accent' => $theme['accent']])
            ->filter()
            ->map(fn (string $color, string $var) => $var.':'.$color)
            ->implode(';');
    }

    /**
     * Варіанти для вибору в адмінці (порожній ключ = звичайний вигляд).
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return ['' => 'Звичайний вигляд'] + array_map(fn (array $theme) => $theme['label'], static::all());
    }

    /** @return array<string, string> підказки «коли вмикати» для варіантів */
    public static function descriptions(): array
    {
        return ['' => 'Без прикрас'] + array_map(fn (array $theme) => $theme['season'], static::all());
    }
}
