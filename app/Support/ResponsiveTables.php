<?php

namespace App\Support;

class ResponsiveTables
{
    /** Прокрутка належить обгортці: таблиця зберігає спільну сітку всіх рядків. */
    public static function render(?string $html): string
    {
        return preg_replace_callback(
            '~<!--.*?-->|<(script|style|textarea)\b[^>]*>.*?</\1\s*>(*SKIP)(*F)|</?table\b(?:"[^"]*"|\'[^\']*\'|[^\'">])*>~is',
            function (array $tag): string {
                if (str_starts_with($tag[0], '<!--')) {
                    return $tag[0];
                }

                return str_starts_with($tag[0], '</')
                    ? $tag[0].'</div>'
                    : '<div class="content-table-scroll" tabindex="0">'.$tag[0];
            },
            $html ?? ''
        ) ?? (string) $html;
    }
}
