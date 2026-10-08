<?php

namespace App\Support;

/**
 * Вступ сторінки-розділу (хаба) над картками дочірніх сторінок.
 *
 * Імпортовані розділи часто дублюють картки рядками «Видання в сфері IT - Посилання».
 * Такі короткі абзаци/пункти списку, що лише ведуть на дочірню сторінку, прибираються
 * лише при виведенні — HTML у БД не змінюється.
 */
class HubIntro
{
    /** Довший абзац із посиланням — уже змістовний текст, а не дубль картки. */
    private const MAX_LINK_LINE = 200;

    /** @param  iterable<string>  $childSlugs */
    public static function withoutChildLinks(?string $html, iterable $childSlugs): string
    {
        $html = (string) $html;
        $paths = [];
        foreach ($childSlugs as $slug) {
            $paths['/'.trim((string) $slug, '/')] = true;
        }
        if ($html === '' || $paths === []) {
            return $html;
        }

        $html = preg_replace_callback(
            '~<(p|li)\b[^>]*>(?:(?!<\1\b).)*?</\1\s*>~isu',
            function (array $block) use ($paths): string {
                if (mb_strlen(trim(html_entity_decode(strip_tags($block[0])))) > self::MAX_LINK_LINE) {
                    return $block[0];
                }
                preg_match_all('~<a\b[^>]*\bhref\s*=\s*(["\'])(.*?)\1~is', $block[0], $links);
                foreach ($links[2] as $href) {
                    if (isset($paths[self::path($href)])) {
                        return '';
                    }
                }

                return $block[0];
            },
            $html
        ) ?? $html;

        // Списки, з яких прибрали всі пункти
        $html = preg_replace('~<(ul|ol)\b[^>]*>\s*</\1\s*>~i', '', $html) ?? $html;

        return trim(strip_tags($html, '<img><iframe>')) === '' ? '' : trim($html);
    }

    private static function path(string $href): string
    {
        $href = html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $path = (string) parse_url($href, PHP_URL_PATH);
        $host = parse_url($href, PHP_URL_HOST);
        if ($host !== null && $host !== request()->getHost() && ! in_array($host, ['otfk.od.ua', 'www.otfk.od.ua'], true)) {
            return '';
        }
        $path = '/'.trim(preg_replace('~^/en(?=/|$)~', '', $path) ?? $path, '/');

        return rawurldecode($path);
    }
}
