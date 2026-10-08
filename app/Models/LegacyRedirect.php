<?php

namespace App\Models;

use App\Support\LegacyRedirects;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Запис карти старих адрес: постійний редирект (301/308) на відносну адресу
 * сайту або 410 Gone для свідомо видаленого матеріалу. Перевірки структури
 * (зовнішнє призначення, цикл, ланцюжок) виконуються при кожному збереженні.
 */
class LegacyRedirect extends Model
{
    public const REDIRECT = 'redirect';

    public const GONE = 'gone';

    public const REDIRECT_CODES = [301, 308];

    protected $fillable = ['source_path', 'source_query', 'action', 'target_url', 'status_code', 'is_active', 'note'];

    protected $attributes = ['action' => self::REDIRECT, 'status_code' => 301, 'is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'status_code' => 'integer', 'hits' => 'integer', 'last_hit_at' => 'datetime'];
    }

    /** Повна стара адреса для таблиці адмінки. */
    public function getSourceAttribute(): string
    {
        return $this->source_path.($this->source_query !== null ? '?'.$this->source_query : '');
    }

    /**
     * Помилки запису карти (порожній масив — запис коректний).
     *
     * @return array<string, string>
     */
    public static function problems(string $path, ?string $query, string $action, ?string $target, int $code, ?int $ignoreId = null): array
    {
        $errors = [];
        $hash = LegacyRedirects::hash($path, $query);

        if (LegacyRedirects::skipped($path)) {
            $errors['source'] = 'Службові адреси (/admin, /livewire…) не перенаправляються.';
        }

        if ($action === self::GONE) {
            if ($code !== 410) {
                $errors['status_code'] = 'Для видаленого матеріалу код відповіді — 410.';
            }
            if (filled($target)) {
                $errors['target_url'] = 'Відповідь 410 не має адреси призначення.';
            }

            return $errors;
        }

        if ($action !== self::REDIRECT) {
            return $errors + ['action' => 'Невідома дія.'];
        }
        if (! in_array($code, self::REDIRECT_CODES, true)) {
            $errors['status_code'] = 'Постійний редирект — 301 або 308.';
        }
        if ($error = self::targetError($target)) {
            return $errors + ['target_url' => $error];
        }

        [$targetHash, $targetPathHash] = self::targetHashes($target);
        if ($targetHash === $hash || $targetPathHash === $hash) {
            $errors['target_url'] = 'Адреса призначення збігається зі старою — це цикл.';
        } elseif (self::activeSources()->whereIn('source_hash', [$targetHash, $targetPathHash])
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $errors['target_url'] = 'Призначення саме є старою адресою іншого редиректу — вийде ланцюжок.';
        }

        // Інший редирект уже веде на цю стару адресу — новий запис зробив би ланцюжок.
        $pointing = self::activeSources()->where('action', self::REDIRECT)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->where(fn ($q) => $q->where('target_hash', $hash)
                ->when($query === null, fn ($q) => $q->orWhere('target_path_hash', $hash)))
            ->exists();
        if ($pointing) {
            $errors['source'] = 'На цю стару адресу вже веде інший редирект — вийде ланцюжок.';
        }

        return $errors;
    }

    /** Лише відносна адреса цього сайту: без схеми, домену, керуючих символів і службових розділів. */
    public static function targetError(?string $target): ?string
    {
        $target = (string) $target;
        if ($target === '') {
            return 'Вкажіть адресу призначення.';
        }
        if (! str_starts_with($target, '/') || str_starts_with($target, '//') || str_contains($target, '\\')
            || preg_match('/[\s\x00-\x1F\x7F]/u', $target) || parse_url($target) === false || parse_url($target, PHP_URL_HOST)) {
            return 'Призначення — лише відносна адреса цього сайту, що починається з «/» (зовнішні адреси заборонені).';
        }
        if (mb_strlen($target) > 2000) {
            return 'Адреса призначення задовга.';
        }
        if (LegacyRedirects::skipped((string) parse_url($target, PHP_URL_PATH))) {
            return 'Службові розділи не можуть бути призначенням.';
        }

        return null;
    }

    /**
     * Хеші нормалізованого призначення: повний (path + значущі параметри) і лише шлях.
     *
     * @return array{0: ?string, 1: ?string}
     */
    public static function targetHashes(?string $target): array
    {
        if (blank($target)) {
            return [null, null];
        }
        [$path, $query] = LegacyRedirects::normalize((string) parse_url($target, PHP_URL_PATH), parse_url($target, PHP_URL_QUERY));

        return [LegacyRedirects::hash($path, $query), LegacyRedirects::hash($path, null)];
    }

    private static function activeSources()
    {
        return static::query()->where('is_active', true);
    }

    protected static function booted(): void
    {
        static::saving(function (self $redirect) {
            [$path, $query] = LegacyRedirects::normalize((string) $redirect->source_path, $redirect->source_query);
            $redirect->source_path = $path;
            $redirect->source_query = $query;
            $redirect->source_hash = LegacyRedirects::hash($path, $query);
            if ($redirect->action === self::GONE) {
                $redirect->target_url = null;
            }
            [$redirect->target_hash, $redirect->target_path_hash] = self::targetHashes($redirect->target_url);

            if ($redirect->is_active) {
                $errors = self::problems($path, $query, (string) $redirect->action, $redirect->target_url, (int) $redirect->status_code, $redirect->id);
                if ($errors !== []) {
                    throw ValidationException::withMessages($errors);
                }
            }
        });

        static::saved(function (): void {
            LegacyRedirects::flush();
        });
        static::deleted(function (): void {
            LegacyRedirects::flush();
        });
    }
}
