<?php

namespace App\Models\Concerns;

use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Cache;

/**
 * Скидає кеш sitemap.xml після збереження чи видалення матеріалу, що входить
 * до карти. Лічильники переглядів/лайків оновлюються повз Eloquent і кеш не чіпають.
 */
trait FlushesSitemap
{
    public static function bootFlushesSitemap(): void
    {
        // Нічого не повертаємо: false від слухача зупинив би решту (NewsObserver/Telegram).
        $flush = function (): void {
            Cache::forget(SitemapController::CACHE_KEY);
        };

        static::saved($flush);
        static::deleted($flush);
    }
}
