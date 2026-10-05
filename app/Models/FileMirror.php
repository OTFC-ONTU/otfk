<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Файл старого сайту, скопійований на публічний диск. Запис ставиться в чергу
 * (status=pending), команда otfk:mirror-files завантажує файл і заповнює path/sha256.
 */
class FileMirror extends Model
{
    public const PENDING = 'pending';

    public const DONE = 'done';

    public const FAILED = 'failed';

    protected $fillable = ['source_url', 'source_hash', 'path', 'status', 'attempts', 'error', 'size', 'sha256', 'mime', 'fetched_at'];

    protected function casts(): array
    {
        return ['fetched_at' => 'datetime', 'size' => 'integer', 'attempts' => 'integer'];
    }

    /** Ставить URL у чергу (повторний виклик для того самого URL нічого не дублює). */
    public static function enqueue(string $url): self
    {
        $url = trim($url);

        return static::query()->firstOrCreate(
            ['source_hash' => hash('sha256', $url)],
            ['source_url' => $url, 'status' => self::PENDING],
        );
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::PENDING);
    }

    /**
     * Відносний публічний URL файлу (/storage/...), сегменти закодовані.
     * Саме його вставляють у контент: домен у БД не зберігається, тож переїзд на інший хостинг посилань не ламає.
     */
    public function publicUrl(): ?string
    {
        if ($this->status !== self::DONE || blank($this->path)) {
            return null;
        }

        return '/storage/'.implode('/', array_map('rawurlencode', explode('/', $this->path)));
    }
}
