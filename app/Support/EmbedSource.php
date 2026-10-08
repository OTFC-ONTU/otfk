<?php

namespace App\Support;

use App\Support\Sanitizer\IframeSourceSanitizer;

/**
 * Адреса для вбудовування PDF/відео в текст (кнопка «PDF / відео» редактора): звичайне
 * посилання, яке скопіював редактор, перетворюється на адресу для <iframe>, дозволену
 * IframeSourceSanitizer. Файли власного сайту зберігаються відносним шляхом (/storage/...).
 */
class EmbedSource
{
    public const KIND_VIDEO = 'video';

    public const KIND_PDF = 'pdf';

    public const KIND_DOCUMENT = 'document';

    public const KIND_MAP = 'map';

    /** @return array{src: string, kind: string}|null null — адресу не можна вбудувати */
    public static function normalize(?string $url): ?array
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        $url = self::ownSiteToRelative($url);
        $result = self::youtube($url) ?? self::google($url) ?? self::pdf($url);

        return $result !== null && IframeSourceSanitizer::allowed($result['src']) ? $result : null;
    }

    /** Тип уже вбудованого вмісту за адресою iframe (для підпису в редакторі й тестів). */
    public static function kind(string $src): string
    {
        return match (true) {
            (bool) preg_match('~(?:youtube(?:-nocookie)?\.com|youtu\.be)/~i', $src) => self::KIND_VIDEO,
            (bool) preg_match('~(?:google\.com/maps|maps\.google\.com)~i', $src) => self::KIND_MAP,
            (bool) preg_match('~(?:docs|drive)\.google\.com/~i', $src) => self::KIND_DOCUMENT,
            default => self::KIND_PDF,
        };
    }

    private static function ownSiteToRelative(string $url): string
    {
        $parts = parse_url($url);
        $own = parse_url((string) config('app.url'), PHP_URL_HOST);
        $host = strtolower((string) ($parts['host'] ?? ''));

        if ($host !== '' && $own && preg_replace('/^www\./', '', $host) === preg_replace('/^www\./', '', strtolower($own))) {
            return ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');
        }

        return $url;
    }

    /** @return array{src: string, kind: string}|null */
    private static function youtube(string $url): ?array
    {
        if (! preg_match('~^https?://(?:(?:www|m)\.)?(?:youtube(?:-nocookie)?\.com|youtu\.be)(/[^?#]*)?(?:\?([^#]*))?~i', $url, $match)) {
            return null;
        }
        parse_str($match[2] ?? '', $query);
        $path = $match[1] ?? '';

        $id = match (true) {
            str_contains(strtolower($url), 'youtu.be/') => trim($path, '/'),
            (bool) preg_match('~^/(?:embed|shorts|live|v)/([^/]+)~', $path, $segment) => $segment[1],
            default => (string) ($query['v'] ?? ''),
        };
        if (! preg_match('~^[A-Za-z0-9_-]{6,20}$~', $id)) {
            return null;
        }

        $start = (string) ($query['start'] ?? $query['t'] ?? '');
        $seconds = preg_match('~^(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s?)?$~', $start, $time) && $start !== ''
            ? (int) ($time[1] ?? 0) * 3600 + (int) ($time[2] ?? 0) * 60 + (int) ($time[3] ?? 0)
            : 0;

        return ['src' => 'https://www.youtube.com/embed/'.$id.($seconds > 0 ? '?start='.$seconds : ''), 'kind' => self::KIND_VIDEO];
    }

    /** @return array{src: string, kind: string}|null */
    private static function google(string $url): ?array
    {
        if (preg_match('~^https://drive\.google\.com/(?:file/d/|open\?id=)([A-Za-z0-9_-]+)~', $url, $match)) {
            return ['src' => 'https://drive.google.com/file/d/'.$match[1].'/preview', 'kind' => self::KIND_DOCUMENT];
        }
        if (preg_match('~^https://docs\.google\.com/(document|presentation|spreadsheets)/d/([A-Za-z0-9_-]+)~', $url, $match)) {
            return ['src' => 'https://docs.google.com/'.$match[1].'/d/'.$match[2].'/preview', 'kind' => self::KIND_DOCUMENT];
        }
        if (preg_match('~^https://(?:www\.)?google\.com/maps/embed\?~', $url)) {
            return ['src' => $url, 'kind' => self::KIND_MAP];
        }

        return null;
    }

    /** @return array{src: string, kind: string}|null */
    private static function pdf(string $url): ?array
    {
        $path = (string) parse_url($url, PHP_URL_PATH);

        return preg_match('~\.pdf$~i', $path) ? ['src' => $url, 'kind' => self::KIND_PDF] : null;
    }
}
