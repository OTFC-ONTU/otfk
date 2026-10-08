<?php

namespace App\Support;

/**
 * Ліниве завантаження зображень і iframe у HTML редактора (лише під час
 * виводу, БД не змінюється): `loading="lazy"` + `decoding="async"` для <img>,
 * `loading="lazy"` для <iframe> (YouTube, карти, документи). Наявні атрибути
 * loading/decoding не перезаписуються. Коментарі, script/style/textarea
 * пропускаються, як у ResponsiveTables.
 *
 * Локальним зображенням без розмірів додаються природні width/height (ImageDimensions).
 *
 * $eagerFirst — перше зображення лишається без lazy: на сторінці без
 * обкладинки воно може бути головним (LCP) елементом першого екрана.
 */
class LazyMedia
{
    public static function render(?string $html, bool $eagerFirst = false): string
    {
        $seenImage = false;

        return preg_replace_callback(
            '~<!--.*?-->|<(script|style|textarea)\b[^>]*>.*?</\1\s*>(*SKIP)(*F)|<(img|iframe)\b((?:"[^"]*"|\'[^\']*\'|[^\'">])*)>~is',
            function (array $match) use ($eagerFirst, &$seenImage): string {
                if (str_starts_with($match[0], '<!--')) {
                    return $match[0];
                }

                $tag = strtolower($match[2]);
                $attributes = $match[3];
                $selfClosing = (bool) preg_match('~/\s*$~', $attributes);
                $attributes = rtrim(preg_replace('~/\s*$~', '', $attributes) ?? $attributes);
                $add = '';

                $isFirstImage = $tag === 'img' && ! $seenImage;
                if ($tag === 'img') {
                    $seenImage = true;
                }

                if (! self::has($attributes, 'loading') && ! ($isFirstImage && $eagerFirst)) {
                    $add .= ' loading="lazy"';
                }
                if ($tag === 'img' && ! self::has($attributes, 'decoding')) {
                    $add .= ' decoding="async"';
                }
                // Файл сайту без розмірів (імпорт): природні width/height резервують місце до завантаження —
                // без зсуву макета (CLS) і без «недольоту» переходу до якоря нижче на сторінці
                if ($tag === 'img' && ! self::has($attributes, 'width') && ! self::has($attributes, 'height')
                    && ($dims = ImageDimensions::of(self::storagePath($attributes)))) {
                    $add .= ' width="'.$dims['width'].'" height="'.$dims['height'].'"';
                }

                return '<'.$match[2].$attributes.$add.($selfClosing ? ' />' : '>');
            },
            $html ?? ''
        ) ?? (string) $html;
    }

    /** Шлях на диску public для src="/storage/…" (відносного або з хостом сайту). */
    private static function storagePath(string $attributes): ?string
    {
        if (! preg_match('~(?:^|\s)src\s*=\s*(["\'])(?:https?://[^/"\']+)?/storage/([^"\'?#]+)\1~i', $attributes, $match)) {
            return null;
        }

        return rawurldecode(html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private static function has(string $attributes, string $name): bool
    {
        return (bool) preg_match('~(?:^|\s)'.$name.'\s*=~i', $attributes);
    }
}
