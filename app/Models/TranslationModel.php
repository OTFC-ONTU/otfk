<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Модель Claude, доступна за ключем API (кеш Models API на тиждень, сторінка «Автопереклад»).
 */
class TranslationModel extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'display_name', 'max_tokens', 'supports_effort', 'supports_structured_outputs', 'released_at', 'fetched_at'];

    protected function casts(): array
    {
        return [
            'max_tokens' => 'integer',
            'supports_effort' => 'boolean',
            'supports_structured_outputs' => 'boolean',
            'released_at' => 'datetime',
            'fetched_at' => 'datetime',
        ];
    }
}
