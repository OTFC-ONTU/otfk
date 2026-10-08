<?php

namespace App\Support;

use Illuminate\View\ComponentAttributeBag;

class FileCards
{
    private const EXTENSIONS = 'pdf|docx?|xlsx?|pptx?|pps|ppsx|odt|ods|odp|rtf|zip|rar|7z';

    /** Єдиний вигляд файлових абзаців, списків і вбудованих PDF; оригінал у БД незмінний. */
    public static function render(?string $html): string
    {
        $parts = preg_split('~(<!--.*?-->|<(?:script|style|textarea)\b[^>]*>.*?</(?:script|style|textarea)\s*>)~is', (string) $html, -1, PREG_SPLIT_DELIM_CAPTURE);

        return implode('', array_map(fn ($part, $index) => $index % 2 ? $part : self::renderFragment($part), $parts, array_keys($parts)));
    }

    private static function renderFragment(string $html): string
    {
        $html = preg_replace_callback(
            '~<(p|li)\b[^>]*>\s*(?:(\d+[.)])\s*)?(?:<strong>\s*)?(<a\b(?:"[^"]*"|\x27[^\x27]*\x27|[^\x27">])*>)(.*?)</a>\s*(?:</strong>\s*)?</\1>~isu',
            function (array $match): string {
                $url = self::attribute($match[3], 'href');
                $extension = self::extension($url);
                if ($extension === null) {
                    return $match[0];
                }
                // Ручна нумерація оригіналу («1)», «2.») лишається частиною назви картки
                $card = self::card($url, trim($match[2].' '.self::text($match[4])), $extension);

                return strtolower($match[1]) === 'li' ? '<li class="file-card-list">'.$card.'</li>' : $card;
            },
            $html
        ) ?? $html;

        $original = $html;

        return preg_replace_callback(
            '~<p\b[^>]*>\s*(<iframe\b[^>]*>.*?</iframe>)\s*</p>|(<iframe\b[^>]*>.*?</iframe>)~is',
            function (array $match) use ($original): string {
                $iframe = ($match[1][0] ?? '') ?: ($match[2][0] ?? '');
                $url = self::attribute($iframe, 'src');
                if (self::extension($url) !== 'pdf') {
                    return $match[0][0];
                }
                $title = self::attribute($iframe, 'title');
                if ($title === '') {
                    preg_match_all('~<(?:h[1-6]|p|summary)\b[^>]*>(.*?)</(?:h[1-6]|p|summary)>~is', substr($original, 0, $match[0][1]), $headings);
                    $title = self::text(end($headings[1]) ?: '');
                }
                if ($title === '') {
                    $title = rawurldecode(basename(parse_url($url, PHP_URL_PATH) ?: $url));
                }

                return self::card($url, $title, 'pdf');
            },
            $html,
            -1,
            $count,
            PREG_OFFSET_CAPTURE
        ) ?? $html;
    }

    public static function card(string $url, string $title, string $extension = 'pdf'): string
    {
        return view('components.file-card', [
            'href' => $url, 'title' => $title, 'extension' => $extension,
            'download' => str_starts_with($url, '/storage/'),
            'attributes' => new ComponentAttributeBag,
        ])->render();
    }

    private static function extension(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '';

        return preg_match('~\.('.self::EXTENSIONS.')$~i', $path, $match) ? strtolower($match[1]) : null;
    }

    private static function attribute(string $tag, string $name): string
    {
        preg_match_all('~\s+([\w:-]+)\s*=\s*(?:"([^"]*)"|\x27([^\x27]*)\x27|([^\s>]+))~', $tag, $attributes, PREG_SET_ORDER);
        foreach ($attributes as $attribute) {
            if (strtolower($attribute[1]) === $name) {
                return html_entity_decode($attribute[2] ?: ($attribute[3] ?? '') ?: ($attribute[4] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        return '';
    }

    private static function text(string $html): string
    {
        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
