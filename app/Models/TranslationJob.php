<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Запис черги автоперекладу (App\Services\AutoTranslation\AutoTranslator) і водночас
 * журнал запиту до Claude API: токени та приблизна вартість для дашборда.
 * Статуси: pending → processing → done | failed | skipped (оригінал змінився під час перекладу
 * або переклад уже не потрібен); failed → retried після «Повторити невдалі». billed_at — час останнього
 * оплачуваного запиту (за ним вартість відноситься до місяця).
 */
class TranslationJob extends Model
{
    public const PENDING = 'pending';

    public const PROCESSING = 'processing';

    public const DONE = 'done';

    public const FAILED = 'failed';

    public const SKIPPED = 'skipped';

    /** Невдалий запис, для якого кнопкою «Повторити невдалі» створено новий запис черги. */
    public const RETRIED = 'retried';

    protected $fillable = [
        'translatable_type', 'translatable_id', 'label', 'status', 'attempts', 'model', 'fields',
        'input_tokens', 'output_tokens', 'cache_read_tokens', 'cache_write_tokens', 'cost_usd',
        'billed_at', 'error', 'locked_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'cache_read_tokens' => 'integer',
            'cache_write_tokens' => 'integer',
            'cost_usd' => 'float',
            'billed_at' => 'datetime',
            'locked_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function translatable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [self::PENDING, self::PROCESSING]);
    }
}
