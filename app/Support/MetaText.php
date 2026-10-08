<?php

namespace App\Support;

/**
 * Короткий текст для meta description / og:description.
 *
 * Бере перше непорожнє джерело (наприклад, meta_description → excerpt → тіло),
 * прибирає HTML, службові коментарі й зайві пробіли та обрізає за словом.
 * Працює лише при виводі, у БД нічого не змінює.
 */
final class MetaText
{
    /** Орієнтовна довжина сніпета пошукових систем. */
    public const LIMIT = 160;

    /** Роздільник заголовка й бренду (приклад плану: «Назва в Одесі — ОТФК ОНТУ»). */
    public const TITLE_SEPARATOR = ' — ';

    /** Перше непорожнє джерело після очищення, обрізане до LIMIT символів. */
    public static function from(?string ...$sources): ?string
    {
        foreach ($sources as $source) {
            $text = self::clean($source);

            if ($text !== '') {
                return self::limit($text);
            }
        }

        return null;
    }

    /** Простий текст без розмітки: скрипти/стилі/коментарі прибираються разом із вмістом. */
    public static function clean(?string $html): string
    {
        if ($html === null || $html === '') {
            return '';
        }

        $text = (string) preg_replace(['/<!--.*?-->/s', '/<(script|style)\b[^>]*>.*?<\/\1>/is'], ' ', $html);
        // Блокові теги розділяємо пробілом, щоб слова сусідніх абзаців не злипалися
        $text = (string) preg_replace('/<\/?(p|div|br|li|h[1-6]|tr|td|th|section|article|blockquote)\b[^>]*>/i', ' ', $text);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/[\s\x{00A0}]+/u', ' ', $text));
    }

    /**
     * Повний <title>: «Заголовок — Бренд». Бренд не додається вдруге, якщо
     * заголовок (наприклад, meta_title редактора) уже містить бренд, назву сайту
     * чи абревіатуру. Без заголовка — лише назва сайту (головна).
     *
     * @param  array<int, string|null>  $aliases  інші написання бренду для перевірки повтору
     */
    public static function title(?string $title, string $siteName, string $brand, array $aliases = []): string
    {
        $title = trim((string) $title);

        if ($title === '') {
            return $siteName;
        }

        // Абревіатури з короткого бренду («ОТФК», «ОНТУ» з «ВСП ОТФК ОНТУ») теж вважаються повтором;
        // слова до 3 літер («ВСП») надто загальні для перевірки
        $abbrs = array_filter(
            preg_split('/\s+/u', $brand) ?: [],
            fn (string $word) => mb_strlen($word) >= 4 && $word === mb_strtoupper($word),
        );
        $variants = array_filter([$brand, $siteName, ...$abbrs, ...$aliases], 'filled');

        foreach ($variants as $variant) {
            if (mb_stripos($title, trim((string) $variant)) !== false) {
                return $title;
            }
        }

        return $title.self::TITLE_SEPARATOR.$brand;
    }

    /** Обрізання за межею слова з трьома крапками. */
    public static function limit(string $text, int $limit = self::LIMIT): string
    {
        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        $cut = mb_substr($text, 0, $limit - 1);
        $space = mb_strrpos($cut, ' ');

        if ($space !== false && $space > $limit * 0.6) {
            $cut = mb_substr($cut, 0, $space);
        }

        return rtrim($cut, ' ,;:.-–—').'…';
    }
}
