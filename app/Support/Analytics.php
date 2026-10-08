<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Http\Request;

/**
 * Google Analytics 4 з консервативною згодою (docs/seo-plan.md, «Аналітика»).
 *
 * Банер згоди виводиться гостям на будь-якому домені, щойно адміністратор
 * задав Measurement ID («SEO → Розмітка та аналітика»): на тестовому хостингу
 * він поводиться так само, як на основному (кнопки, збережений вибір,
 * «Налаштування cookies»). Але ID і дозвіл завантажити gtag отримує лише
 * основний домен (Seo::indexable()) — на інших доменах тег Google не
 * завантажується ніколи. Персонал, що увійшов до адмінки, банера не бачить.
 * Сервер ніколи не віддає скрипт googletagmanager: його підвантажує
 * resources/js/analytics.js лише після натискання «Прийняти».
 */
class Analytics
{
    public const MEASUREMENT_ID_KEY = 'ga4_measurement_id';

    /** Формат ідентифікатора потоку GA4. */
    public const MEASUREMENT_ID_PATTERN = '/^G-[A-Z0-9]{4,20}$/';

    /**
     * Версія тексту згоди: збільшення знову показує банер усім, хто вже
     * обрав (напр., після зміни політики конфіденційності).
     */
    public const CONSENT_VERSION = 1;

    /** Збережений ідентифікатор, якщо він має правильний формат. */
    public static function measurementId(): ?string
    {
        $id = strtoupper(trim((string) Setting::get(self::MEASUREMENT_ID_KEY)));

        return preg_match(self::MEASUREMENT_ID_PATTERN, $id) === 1 ? $id : null;
    }

    /** Чи виводити банер згоди й посилання «Налаштування cookies» (будь-який домен). */
    public static function bannerVisible(): bool
    {
        return self::measurementId() !== null && ! auth()->check();
    }

    /** Чи віддати ID і дозволити завантаження gtag після згоди — лише основний домен. */
    public static function tracking(?Request $request = null): bool
    {
        return self::bannerVisible() && Seo::indexable($request);
    }
}
