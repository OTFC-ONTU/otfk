<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Природні розміри зображення з публічного диска — для атрибутів width/height
 * (браузер резервує місце до завантаження, без зсуву макета, CLS).
 *
 * Лише читання заголовка файла (getimagesize) з кешем за шляхом і часом зміни;
 * жодної обробки чи нових файлів. SVG і недоступні файли повертають null —
 * тоді атрибути просто не виводяться.
 */
class ImageDimensions
{
    /** @return array{width:int, height:int}|null */
    public static function of(?string $path): ?array
    {
        if (! filled($path) || str_contains($path, '..')) {
            return null;
        }

        $disk = Storage::disk('public');
        $full = $disk->path($path);
        $mtime = @filemtime($full);

        if ($mtime === false) {
            return null;
        }

        $cached = Cache::remember('image_dims.'.sha1($path.'|'.$mtime), 30 * 86400, function () use ($full) {
            $size = @getimagesize($full);

            // false не кешується як «відсутність» — зберігаємо порожній масив
            return $size && $size[0] > 0 && $size[1] > 0 ? ['width' => (int) $size[0], 'height' => (int) $size[1]] : [];
        });

        return $cached ?: null;
    }
}
