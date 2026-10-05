<?php

namespace App\Support;

class LocalizedHtml
{
    /** Змінює тільки href посилань, зберігаючи розмітку та оригінал у БД. */
    public static function links(?string $html): string
    {
        return preg_replace_callback(
            '~<!--.*?-->|<(script|style|textarea)\b[^>]*>.*?</\1\s*>(*SKIP)(*F)|<a\b(?:"[^"]*"|\'[^\']*\'|[^\'">])*>~is',
            function (array $tag): string {
                if (str_starts_with($tag[0], '<!--')) {
                    return $tag[0];
                }

                // Розбираємо всі атрибути, щоб не зачепити href усередині іншого атрибута.
                return preg_replace_callback(
                    '~(\s+)([\w:-]+)(\s*=\s*)(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))~',
                    function (array $attribute): string {
                        if (strtolower($attribute[2]) !== 'href') {
                            return $attribute[0];
                        }

                        $value = substr($attribute[0], strlen($attribute[1].$attribute[2].$attribute[3]));
                        $raw = in_array($value[0], ['"', "'"], true) ? substr($value, 1, -1) : $value;
                        $url = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                        $localized = LocalizedUrl::to($url);

                        if ($localized === $url) {
                            return $attribute[0];
                        }

                        return $attribute[1].$attribute[2].$attribute[3].'"'.htmlspecialchars($localized, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'"';
                    },
                    $tag[0]
                );
            },
            $html ?? ''
        );
    }
}
