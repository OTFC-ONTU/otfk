<?php

namespace App\Support;

/**
 * Якір блоку «Викладацький склад» на сторінці підрозділу (/struktura/{slug}#vykladachi).
 *
 * Імпортовані описи цикловових комісій посилаються на окремі старі сторінки
 * «Викладачі комісії …» (/vikladaci-komisiyi-…), хоча нижче на тій самій сторінці вже є картки
 * викладачів. Якщо картки є, такі посилання ведуть на якір блоку. Лише при виведенні:
 * HTML у БД не змінюється. У редакторі на блок можна послатися адресою «#vykladachi».
 */
class DepartmentStaffAnchor
{
    public const ID = 'vykladachi';

    public static function links(?string $html): string
    {
        return (string) preg_replace(
            '~(<a\b[^>]*\shref\s*=\s*)(["\'])(?:https?://[^/"\']+)?/(?:en/)?vikladaci-komisiyi-[^"\'#?]*(?:[?#][^"\']*)?\2~i',
            '$1$2#'.self::ID.'$2',
            (string) $html,
        );
    }
}
