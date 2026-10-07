<?php

namespace App\Support\Sanitizer;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * Атрибут class: у збірці сайту є утиліти Tailwind (fixed, inset-0, z-50,
 * opacity-0, translate-*, w-screen…), якими можна перекрити сторінку або
 * приховати її елементи так само, як інлайн-стилем. Класи позиціонування,
 * накладання, прозорості, трансформацій і повноекранних розмірів
 * відкидаються (з урахуванням варіантів `md:`/`hover:` і від'ємних `-`),
 * решта (оформлення тексту, таблиць, імпортовані класи старого сайту) лишається.
 */
class ClassAttributeSanitizer implements AttributeSanitizerInterface
{
    private const BLOCKED = '~^-?(?:fixed|absolute|sticky|static|relative|inset|top|bottom|left|right|start|end|z|opacity|translate|scale|rotate|skew|transform|origin|w-screen|h-screen|min-h-screen|max-h-screen|min-w-screen|h-dvh|h-svh|h-lvh|w-dvw|backdrop|pointer-events|invisible|collapse|sr-only|size-screen)(?:-|$|\[)~';

    public function getSupportedElements(): ?array
    {
        return null;
    }

    public function getSupportedAttributes(): ?array
    {
        return ['class'];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        $kept = [];

        foreach (preg_split('/\s+/u', trim($value)) ?: [] as $class) {
            if ($class === '') {
                continue;
            }

            // Варіанти Tailwind (md:fixed, hover:opacity-0) — перевіряємо утиліту після останньої двокрапки.
            $utility = str_contains($class, ':') ? substr($class, strrpos($class, ':') + 1) : $class;

            if (preg_match(self::BLOCKED, $utility)) {
                continue;
            }

            $kept[] = $class;
        }

        return $kept === [] ? null : implode(' ', $kept);
    }
}
