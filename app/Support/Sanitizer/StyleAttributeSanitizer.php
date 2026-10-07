<?php

namespace App\Support\Sanitizer;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * Білий список CSS-властивостей для атрибута style: лише оформлення тексту,
 * таблиць і відступів з імпортованої розмірки старого сайту. Властивості
 * позиціонування та накладання (position, z-index, inset, transform, opacity,
 * display тощо) і значення з url()/expression()/javascript відкидаються —
 * інакше редактор міг би перекрити сторінку власним «інтерфейсом».
 */
class StyleAttributeSanitizer implements AttributeSanitizerInterface
{
    private const ALLOWED_PROPERTIES = [
        'color', 'background-color', 'background',
        'font-family', 'font-size', 'font-weight', 'font-style', 'line-height', 'letter-spacing',
        'text-align', 'text-decoration', 'text-indent', 'text-transform', 'vertical-align', 'white-space', 'word-break',
        'width', 'height', 'max-width', 'min-width', 'max-height', 'min-height',
        'margin', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left',
        'padding', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left',
        'border', 'border-top', 'border-right', 'border-bottom', 'border-left',
        'border-color', 'border-style', 'border-width', 'border-collapse', 'border-spacing', 'border-radius',
        'list-style', 'list-style-type', 'table-layout', 'caption-side', 'float', 'clear', 'text-overflow', 'overflow-wrap', 'aspect-ratio',
    ];

    public function getSupportedElements(): ?array
    {
        return null; // усі елементи
    }

    public function getSupportedAttributes(): ?array
    {
        return ['style'];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        $kept = [];

        foreach (explode(';', $value) as $declaration) {
            if (! str_contains($declaration, ':')) {
                continue;
            }

            [$property, $css] = explode(':', $declaration, 2);
            $property = strtolower(trim($property));
            $css = trim($css);

            if (! in_array($property, self::ALLOWED_PROPERTIES, true) || $css === '') {
                continue;
            }

            if (preg_match('~url\s*\(|expression|javascript|@import|\\\\|/\*|<|>~i', $css)) {
                continue;
            }

            $kept[] = $property.': '.$css;
        }

        return $kept === [] ? null : implode('; ', $kept);
    }
}
