<?php

namespace App\Support;

/**
 * Абзаци, що містять лише посилання на файл (PDF, DOC, XLS…), при виводі сторінки
 * показуються карткою файла — як у розділі документів. HTML у БД не змінюється;
 * посилання всередині речень лишаються звичайними.
 */
class FileCards
{
    private const EXTENSIONS = 'pdf|docx?|xlsx?|pptx?|pps|ppsx|odt|ods|odp|rtf|zip|rar|7z';

    public static function render(?string $html): string
    {
        if (blank($html)) {
            return (string) $html;
        }

        return preg_replace_callback(
            '#<p>\s*<a\s+([^>]*?)href="([^"]+\.('.self::EXTENSIONS.')(?:[?\#][^"]*)?)"([^>]*)>((?:(?!</a>).)+)</a>\s*</p>#isu',
            fn (array $m) => self::card($m[2], strtolower($m[3]), $m[5]),
            $html,
        ) ?? $html;
    }

    private static function card(string $href, string $ext, string $label): string
    {
        $url = e(html_entity_decode($href, ENT_QUOTES | ENT_HTML5), false);
        $icon = svg('heroicon-o-document-text', 'h-6 w-6')->toHtml();
        $download = svg('heroicon-o-arrow-down-tray', 'h-4 w-4')->toHtml();
        $title = trim(strip_tags($label, '<strong><b><em><i><br>'));
        $aria = e(__('public.download').': '.trim(strip_tags($label)));

        return '<div class="file-card not-prose">'
            .'<span class="file-card__icon" aria-hidden="true">'.$icon.'</span>'
            .'<span class="file-card__body"><a href="'.$url.'" target="_blank" rel="noopener" class="file-card__title">'.$title.'</a>'
            .'<span class="file-card__meta">'.strtoupper($ext).'</span></span>'
            .'<a href="'.$url.'" target="_blank" rel="noopener" class="file-card__download" aria-label="'.$aria.'">'.$download.'<span class="file-card__download-label">'.e(__('public.download')).'</span></a>'
            .'</div>';
    }
}
