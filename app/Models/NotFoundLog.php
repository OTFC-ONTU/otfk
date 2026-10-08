<?php

namespace App\Models;

use App\Support\LegacyRedirects;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Агрегований журнал 404: одна строка на нормалізовану адресу (шлях + значущі
 * параметри старої CMS). Не зберігає cookies, токени, IP і довільні query;
 * referrer — лише схема, домен і шлях. Записи старші 90 днів видаляє model:prune.
 */
class NotFoundLog extends Model
{
    use MassPrunable;

    public const NEW = 'new';

    public const RESOLVED = 'resolved';

    public const IGNORED = 'ignored';

    public const STATUSES = [self::NEW => 'Нове', self::RESOLVED => 'Вирішено', self::IGNORED => 'Ігнорується'];

    public $timestamps = false;

    protected $fillable = ['path_hash', 'path', 'query', 'referrer', 'hits', 'first_seen_at', 'last_seen_at', 'status'];

    protected function casts(): array
    {
        return ['hits' => 'integer', 'first_seen_at' => 'datetime', 'last_seen_at' => 'datetime'];
    }

    public function getUrlAttribute(): string
    {
        return $this->path.($this->query !== null ? '?'.$this->query : '');
    }

    public function prunable()
    {
        return static::where('last_seen_at', '<', now()->subDays((int) config('otfk.not_found_log.keep_days', 90)));
    }

    /** Фіксує звернення до відсутньої адреси; будь-яка помилка лише репортується. */
    public static function record(Request $request, string $path, ?string $query): void
    {
        try {
            if (mb_strlen($path) > 1000 || $path === '') {
                return;
            }

            $hash = LegacyRedirects::hash($path, $query);
            $now = now();
            $referrer = self::safeReferrer($request->headers->get('referer'));

            $updated = static::query()->where('path_hash', $hash)->toBase()->update(array_filter([
                'hits' => DB::raw('hits + 1'),
                'last_seen_at' => $now,
                'referrer' => $referrer,
            ]));
            if ($updated > 0) {
                // Вирішена адреса знову дає 404 — повертаємо до розбору.
                static::query()->where('path_hash', $hash)->where('status', self::RESOLVED)->toBase()->update(['status' => self::NEW]);

                return;
            }

            // Обмеження розміру журналу проти бот-сміття: нові адреси понад ліміт не пишемо.
            if (static::query()->count() >= (int) config('otfk.not_found_log.max_rows', 20000)) {
                return;
            }

            static::query()->insertOrIgnore([
                'path_hash' => $hash,
                'path' => $path,
                'query' => $query,
                'referrer' => $referrer,
                'hits' => 1,
                'first_seen_at' => $now,
                'last_seen_at' => $now,
                'status' => self::NEW,
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** Referrer без query, fragment і облікових даних: схема, домен, шлях. */
    public static function safeReferrer(?string $referrer): ?string
    {
        $parts = $referrer ? parse_url($referrer) : false;
        if (! $parts || ! isset($parts['host']) || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            return null;
        }

        return Str::limit(strtolower($parts['scheme']).'://'.strtolower($parts['host']).($parts['path'] ?? '/'), 490, '');
    }
}
