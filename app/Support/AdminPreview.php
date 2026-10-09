<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Разові знімки незбереженої форми адмінки для превʼю «як у GitHub wiki»:
 * стан форми кладеться в кеш під випадковим токеном, публічний шаблон
 * рендериться з цих даних без запису в БД. Знімок живе 10 хвилин.
 */
class AdminPreview
{
    private const TTL_MINUTES = 10;

    /** Каталог на public-диску для копій щойно завантажених (ще не збережених) файлів. */
    private const FILES_DIR = 'admin-preview';

    /**
     * Зберегти знімок: атрибути запису (якщо редагується наявний) поверх
     * перекриваються значеннями з форми. Скаляри беруться як є; стан FileUpload
     * (масив) розгортається у шлях — щойно завантажений тимчасовий файл
     * копіюється на public-диск під токеном, щоб шаблон показав його звичайним
     * asset('storage/…'). Інші нескалярні стани пропускаються.
     */
    public static function store(string $type, Model $base, array $state): string
    {
        $token = Str::random(40);
        $attributes = $base->getAttributes();

        foreach ($state as $key => $value) {
            if (! $base->isFillable($key)) {
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $attributes[$key] = $value;
            } elseif (is_array($value) && ($file = self::fileValue($value, $token, $key)) !== false) {
                $attributes[$key] = $file;
            }
        }

        Cache::put(
            self::key($token),
            ['type' => $type, 'attributes' => $attributes],
            now()->addMinutes(self::TTL_MINUTES),
        );

        return $token;
    }

    /** @return array{type: string, attributes: array<string, mixed>}|null */
    public static function get(string $token): ?array
    {
        $snapshot = Cache::get(self::key($token));

        return is_array($snapshot) ? $snapshot : null;
    }

    /**
     * Розгорнути стан FileUpload: порожній масив — файл з форми прибрали (null),
     * рядок — уже збережений шлях, TemporaryUploadedFile — свіже завантаження,
     * яке копіюємо на public-диск. Будь-який інший масив — не FileUpload,
     * повертаємо false («пропустити, лишити збережене значення»).
     *
     * @param  array<mixed>  $value
     * @return string|null|false
     */
    private static function fileValue(array $value, string $token, string $key): string|null|false
    {
        if ($value === []) {
            return null;
        }

        $first = head($value);

        if (is_string($first)) {
            return $first;
        }

        if ($first instanceof TemporaryUploadedFile) {
            self::pruneFiles();

            $extension = strtolower((string) preg_replace(
                '/[^a-z0-9]/i',
                '',
                pathinfo($first->getClientOriginalName(), PATHINFO_EXTENSION),
            )) ?: 'jpg';

            return $first->storeAs(self::FILES_DIR, $token . '-' . $key . '.' . $extension, ['disk' => 'public']);
        }

        return false;
    }

    /** Прибрати протухлі копії превʼю-файлів (знімок у кеші живе TTL_MINUTES). */
    private static function pruneFiles(): void
    {
        $disk = Storage::disk('public');
        $expired = now()->subMinutes(self::TTL_MINUTES)->getTimestamp();

        foreach ($disk->files(self::FILES_DIR) as $file) {
            if ($disk->lastModified($file) < $expired) {
                $disk->delete($file);
            }
        }
    }

    private static function key(string $token): string
    {
        return 'admin-preview:' . $token;
    }
}
