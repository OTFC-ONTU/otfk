<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Місячні лічильники «Скриньки довіри»: надіслано / відхилено як спам /
 * помилки надсилання / Turnstile недоступний. Жодних текстів чи часу.
 */
class TrustBoxStat extends Model
{
    public const COUNTERS = ['sent', 'rejected', 'failed', 'captcha_unavailable'];

    protected $primaryKey = 'month';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    public static function currentMonth(): string
    {
        return now('Europe/Kyiv')->format('Y-m');
    }

    /** Атомарне збільшення лічильника поточного місяця; помилка статистики не ламає відправку. */
    public static function bump(string $counter, int $by = 1): void
    {
        if (! in_array($counter, self::COUNTERS, true) || $by < 1) {
            return;
        }

        try {
            $month = self::currentMonth();
            DB::table('trust_box_stats')->insertOrIgnore(['month' => $month]);
            DB::table('trust_box_stats')->where('month', $month)->increment($counter, $by);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** @return array<string, int> */
    public static function forMonth(?string $month = null): array
    {
        $row = DB::table('trust_box_stats')->where('month', $month ?? self::currentMonth())->first();

        return collect(self::COUNTERS)->mapWithKeys(fn (string $c) => [$c => (int) ($row->{$c} ?? 0)])->all();
    }
}
