<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class BellPeriod extends Model
{
    /** Ключ налаштування-перемикача другої зміни. */
    public const SECOND_SHIFT_SETTING = 'bells_second_shift';

    /** Ключ налаштування-перемикача плашки «зараз іде пара» у верхній смузі. */
    public const NOW_CHIP_SETTING = 'bells_now_chip';

    /** Кількість пар у кожній зміні — фіксована, пари не додаються і не видаляються. */
    public const PAIRS_PER_SHIFT = 4;

    /** Номери змін. Друга ховається перемикачем, але рядки в базі лишаються. */
    public const SHIFTS = [1, 2];

    protected $fillable = ['number', 'shift', 'starts', 'ends', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'shift' => 'integer', 'number' => 'integer'];
    }

    /**
     * Активні пари у порядку змін і часу початку (кешовано для спільного макета).
     * Друга зміна ховається перемикачем у налаштуваннях, самі пари при цьому лишаються в базі.
     */
    public static function active()
    {
        // .v3 містить і зміну, і ID запису для підсвічування перетинів.
        $periods = Cache::remember('bell_periods.v3', 600, fn () => static::query()
            ->where('is_active', true)
            ->orderBy('shift')
            ->orderBy('starts')
            ->orderBy('id')
            ->get(['id', 'number', 'shift', 'starts', 'ends']));

        return static::secondShiftEnabled() ? $periods : $periods->where('shift', 1)->values();
    }

    /** Чи показувати другу зміну на сайті (налаштування `bells_second_shift`, типово — так). */
    public static function secondShiftEnabled(): bool
    {
        return Setting::get(self::SECOND_SHIFT_SETTING, '1') === '1';
    }

    /** Чи показувати плашку «зараз іде пара» у верхній смузі (`bells_now_chip`, типово — так). */
    public static function chipEnabled(): bool
    {
        return Setting::get(self::NOW_CHIP_SETTING, '1') === '1';
    }

    /** Запис перемикача розкладу в `settings` (скидання кешу — в `Setting::booted()`). */
    public static function setFlag(string $key, bool $enabled): void
    {
        Setting::updateOrCreate(
            ['key' => $key],
            ['value' => $enabled ? '1' : '0', 'group' => 'general', 'type' => 'text'],
        );
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('bell_periods.v3'));
        static::deleted(fn () => Cache::forget('bell_periods.v3'));
    }
}
