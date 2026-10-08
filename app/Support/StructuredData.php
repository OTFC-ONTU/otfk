<?php

namespace App\Support;

use App\Models\Setting;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * Спільні частини JSON-LD (docs/seo-plan.md, етап 3): організація-видавець,
 * абсолютні URL, поштова адреса та дати з київським зсувом.
 *
 * Дати: `published_at`/`starts_at` зберігаються як київський wall-clock при
 * config app.timezone = UTC, тому їх «дозсуваємо» (shiftTimezone), а
 * `created_at`/`updated_at` Laravel пише справжнім UTC — їх лише переводимо
 * в київський пояс (setTimezone). Див. Gotcha «Таймзона» в ARCHITECTURE.md.
 */
class StructuredData
{
    public const TIMEZONE = 'Europe/Kyiv';

    /** Ключі налаштувань сторінки «SEO → Розмітка та аналітика». */
    public const ALTERNATE_NAMES_KEY = 'seo_alternate_names';

    public const SAME_AS_KEY = 'seo_same_as';

    /** Максимальна довжина headline, яку приймає Google для статей. */
    public const HEADLINE_LIMIT = 110;

    /** Ідентифікатор вузла організації — на нього посилаються видавець і провайдер. */
    public static function organizationId(): string
    {
        return rtrim(url('/'), '/').'/#organization';
    }

    /** Назва організації в поточній локалі (як у <title> layout). */
    public static function siteName(?array $settings = null): string
    {
        $settings ??= Setting::publicMap();

        return app()->getLocale() === 'en'
            ? ($settings['brand_name'] ?? __('layout.brand_name'))
            : (string) config('app.name');
    }

    /** Абсолютна адреса логотипа; без завантаженого — емблема коледжу 192px. */
    public static function logoUrl(?array $settings = null): string
    {
        $settings ??= Setting::publicMap();

        return filled($settings['logo'] ?? null) ? asset('storage/'.$settings['logo']) : asset('icon-192.png');
    }

    /** Повний вузол EducationalOrganization для layout (порожні поля відкидаються). */
    public static function organization(?array $settings = null): array
    {
        $settings ??= Setting::publicMap();

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'EducationalOrganization',
            '@id' => static::organizationId(),
            'name' => static::siteName($settings),
            'alternateName' => static::alternateNames($settings) ?: null,
            'url' => url('/'),
            'logo' => static::logoUrl($settings),
            'email' => filled($settings['contact_email'] ?? null) ? $settings['contact_email'] : null,
            'telephone' => filled($settings['contact_phone'] ?? null) ? $settings['contact_phone'] : null,
            'address' => static::postalAddress($settings),
            'sameAs' => static::sameAs($settings) ?: null,
        ], fn ($value) => $value !== null);
    }

    /** Коротке посилання на організацію для publisher/author/provider/organizer. */
    public static function publisher(?array $settings = null): array
    {
        $settings ??= Setting::publicMap();

        return [
            '@type' => 'Organization',
            '@id' => static::organizationId(),
            'name' => static::siteName($settings),
            'url' => url('/'),
            'logo' => ['@type' => 'ImageObject', 'url' => static::logoUrl($settings)],
        ];
    }

    /**
     * PostalAddress з адреси контактів. Структурованих полів у налаштуваннях
     * немає, тож увесь рядок іде в streetAddress, місто й країна — сталі.
     */
    public static function postalAddress(?array $settings = null): ?array
    {
        $settings ??= Setting::publicMap();
        $address = trim((string) ($settings['contact_address'] ?? ''));

        if ($address === '') {
            return null;
        }

        return array_filter([
            '@type' => 'PostalAddress',
            'streetAddress' => $address,
            'addressLocality' => __('public.city'),
            'postalCode' => preg_match('/(?<!\d)(\d{5})(?!\d)/', $address, $m) ? $m[1] : null,
            'addressCountry' => 'UA',
        ], fn ($value) => $value !== null);
    }

    /** Колишні назви та абревіатури — по одній у рядку. */
    public static function alternateNames(?array $settings = null): array
    {
        $settings ??= Setting::publicMap();

        return array_values(array_unique(static::lines($settings[self::ALTERNATE_NAMES_KEY] ?? null)));
    }

    /** Офіційні профілі (лише https) — по одному в рядку; невалідні рядки пропускаються. */
    public static function sameAs(?array $settings = null): array
    {
        $settings ??= Setting::publicMap();

        return array_values(array_unique(array_filter(
            static::lines($settings[self::SAME_AS_KEY] ?? null),
            fn (string $url) => static::isHttpsUrl($url),
        )));
    }

    /** Абсолютний https-URL без облікових даних (для sameAs). */
    public static function isHttpsUrl(string $url): bool
    {
        if (preg_match('/[\s<>"\']/u', $url)) {
            return false;
        }
        $parts = parse_url($url);

        return is_array($parts)
            && strtolower($parts['scheme'] ?? '') === 'https'
            && filled($parts['host'] ?? null)
            && ! isset($parts['user'])
            && ! isset($parts['pass'])
            && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /** @return list<string> непорожні рядки textarea без пробілів по краях */
    public static function lines(?string $value): array
    {
        return array_values(array_filter(
            array_map('trim', preg_split('/\R/u', (string) $value) ?: []),
            fn (string $line) => $line !== '',
        ));
    }

    /** Відносний шлях → абсолютний URL; абсолютні та порожні значення без змін. */
    public static function absoluteUrl(?string $url): ?string
    {
        if (! filled($url)) {
            return null;
        }

        return preg_match('~^[a-z][a-z0-9+.-]*://~i', $url) ? $url : url($url);
    }

    /** Заголовок статті не довший за ліміт Google (із трикрапкою). */
    public static function headline(?string $title): string
    {
        return Str::limit((string) $title, self::HEADLINE_LIMIT - 1, '…');
    }

    /** Дата, збережена як київський wall-clock (published_at, starts_at). */
    public static function wallClockDate(?CarbonInterface $date): ?string
    {
        return $date?->copy()->shiftTimezone(self::TIMEZONE)->toIso8601String();
    }

    /** Справжня UTC-позначка Laravel (created_at, updated_at) у київському поясі. */
    public static function utcDate(?CarbonInterface $date): ?string
    {
        return $date?->copy()->setTimezone(self::TIMEZONE)->toIso8601String();
    }
}
