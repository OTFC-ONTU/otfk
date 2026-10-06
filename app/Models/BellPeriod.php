<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class BellPeriod extends Model
{
    protected $fillable = ['number', 'starts', 'ends', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** Активні пари у часовому порядку (кешовано — макет показує їх на кожній сторінці). */
    public static function active()
    {
        return Cache::remember('bell_periods', 600, fn () => static::query()
            ->where('is_active', true)
            ->orderBy('starts')
            ->orderBy('id')
            ->get(['id', 'number', 'starts', 'ends']));
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('bell_periods'));
        static::deleted(fn () => Cache::forget('bell_periods'));
    }
}
