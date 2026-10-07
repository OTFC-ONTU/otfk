<?php

namespace App\Support;

use App\Support\Sanitizer\ClassAttributeSanitizer;
use App\Support\Sanitizer\IframeSourceSanitizer;
use App\Support\Sanitizer\StyleAttributeSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer as SymfonySanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Очищення HTML редактора (тіла сторінок/новин, описи спеціальностей і
 * підрозділів, біографії персоналу) через symfony/html-sanitizer — DOM-парсер
 * з білим списком елементів W3C Sanitizer API, а не регулярні вирази.
 *
 * Лишаються: текстова розмітка, списки, таблиці (з colspan/rowspan/width/
 * border), native details/summary, зображення (http(s), відносні, data:image),
 * посилання http(s)/mailto/tel/відносні, атрибути class/id/style/title/alt,
 * <iframe> лише з YouTube, Google Maps/Docs/Drive або власного сайту
 * (IframeSourceSanitizer). Видаляються: <script>, <style>, <object>, <embed>,
 * обробники on*, srcdoc, javascript:/vbscript:-URL, чужі iframe. Текст
 * з «<» (напр. «a < b») екранується, а не перетворюється на тег.
 *
 * Парсер відкидає коментарі, тому маркери імпорту <!--imported-from:URL-->
 * (їх читають Page::publicBody(), команди імпорту та скрипти синхронізації)
 * зберігаються окремо і дописуються в кінець результату.
 *
 * Застосовується кастом App\Casts\SafeHtml під час запису атрибута (Filament,
 * saveQuietly(), імпорти); уже збережений контент не переписується до
 * наступного збереження.
 */
class HtmlSanitizer
{
    private const MARKER = '<!--imported-from:[^>]*-->';

    private static ?SymfonySanitizer $sanitizer = null;

    public static function clean(?string $html): ?string
    {
        if ($html === null || $html === '' || ! str_contains($html, '<')) {
            return $html;
        }

        if (! mb_check_encoding($html, 'UTF-8')) {
            $html = mb_convert_encoding($html, 'UTF-8', 'UTF-8');
        }

        preg_match_all('/(\n?)('.self::MARKER.')/u', $html, $markers, PREG_SET_ORDER);

        // Парсер кодує «=» та «@» в атрибутах як &#61;/&#64; — у лапках вони безпечні,
        // а читабельний href потрібен LocalizedHtml/LinkChecker і скриптам синхронізації.
        $clean = str_replace(['&#61;', '&#64;'], ['=', '@'], self::sanitizer()->sanitizeFor('body', $html));

        $seen = [];
        foreach ($markers as [, $newline, $marker]) {
            if (! isset($seen[$marker])) {
                $seen[$marker] = true;
                // Як в оригіналі: з переносом рядка або без (парсер може залишити власний «\n» у кінці).
                $clean .= (str_ends_with($clean, "\n") ? '' : $newline).$marker;
            }
        }

        return $clean;
    }

    private static function sanitizer(): SymfonySanitizer
    {
        return self::$sanitizer ??= new SymfonySanitizer(self::config());
    }

    private static function config(): HtmlSanitizerConfig
    {
        $config = (new HtmlSanitizerConfig)
            ->allowSafeElements()
            ->allowElement('iframe', ['src', 'width', 'height', 'allow', 'allowfullscreen', 'frameborder', 'title', 'loading', 'referrerpolicy'])
            ->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])
            ->allowLinkHosts(null)
            ->allowRelativeLinks()
            ->allowMediaSchemes(['http', 'https', 'data'])
            ->allowMediaHosts(null)
            ->allowRelativeMedias()
            ->withAttributeSanitizer(new IframeSourceSanitizer)
            ->withAttributeSanitizer(new StyleAttributeSanitizer)
            ->withAttributeSanitizer(new ClassAttributeSanitizer)
            ->withMaxInputLength(-1);

        // Атрибути імпортованої розмітки старого сайту, які стандарт вважає «небезпечними» лише для оформлення.
        foreach (['class', 'style', 'align', 'valign', 'border', 'cellpadding', 'cellspacing', 'bgcolor', 'frameborder'] as $attribute) {
            $config = $config->allowAttribute($attribute, '*');
        }

        // Нумерація списків і службові дані вкладень Trix (RichEditor Filament) — JSON у data-атрибутах, без скриптів.
        $config = $config->allowAttribute('value', ['li'])->allowAttribute('start', ['ol'])->allowAttribute('reversed', ['ol']);
        foreach (['data-trix-attachment', 'data-trix-content-type', 'data-trix-attributes'] as $attribute) {
            $config = $config->allowAttribute($attribute, ['figure']);
        }

        return $config;
    }
}
