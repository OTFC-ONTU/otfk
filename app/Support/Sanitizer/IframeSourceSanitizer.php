<?php

namespace App\Support\Sanitizer;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * Білий список адрес для <iframe src>: вбудовані відео YouTube, карти Google,
 * опубліковані документи Google Docs/Drive, PDF старого сайту otfk.od.ua та файли власного сайту
 * (абсолютний шлях з одним «/», напр. /storage/...pdf). Усе інше, включно з
 * sites.google.com і Google Forms (сторінки-двійники для фішингу), прибирається;
 * iframe без src лишається порожнім і нічого не показує.
 */
class IframeSourceSanitizer implements AttributeSanitizerInterface
{
    /** @var array<string, string> хост => регулярний вираз для шляху */
    private const ALLOWED = [
        'youtube.com' => '~^/embed/~',
        'youtube-nocookie.com' => '~^/embed/~',
        'youtu.be' => '~^/~',
        'google.com' => '~^/maps/~',
        'maps.google.com' => '~^/~',
        'docs.google.com' => '~^/(document|presentation|spreadsheets)/d/~',
        'drive.google.com' => '~^/file/d/~',
        // Старий сайт коледжу: імпортований контент вбудовує його PDF, поки файл не віддзеркалено у /storage/mirror.
        'otfk.od.ua' => '~\.pdf$~i',
    ];

    public function getSupportedElements(): ?array
    {
        return ['iframe'];
    }

    public function getSupportedAttributes(): ?array
    {
        return ['src'];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        return self::allowed($value) ? $value : null;
    }

    public static function allowed(string $src): bool
    {
        $src = trim($src);

        if ($src === '') {
            return false;
        }

        if (preg_match('~^/(?![/\\\\])~', $src)) {
            return true; // власний файл сайту
        }

        $parts = parse_url($src);
        if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
            return false;
        }

        $host = (string) preg_replace('/^www\./', '', strtolower($parts['host']));
        $path = $parts['path'] ?? '/';

        foreach (self::ALLOWED as $allowedHost => $pathPattern) {
            if ($host === $allowedHost && preg_match($pathPattern, $path)) {
                return true;
            }
        }

        return false;
    }
}
