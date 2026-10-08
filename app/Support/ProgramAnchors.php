<?php

namespace App\Support;

use App\Models\Program;
use App\Models\Specialty;
use Illuminate\Support\Str;

/**
 * Якорі освітньо-професійних програм на сторінці спеціальності.
 *
 * Якір ОПП стабільний для обох локалей (транслітерована оригінальна назва). На сторінці
 * спеціальності він ставиться на заголовок опису, що містить назву програми
 * («ОПП «…»»), а якщо такого заголовка немає — на картку файлу програми в списку ОПП.
 * Лише при виведенні: HTML у БД не змінюється.
 */
class ProgramAnchors
{
    public static function anchor(Program $program): string
    {
        $slug = Str::slug(Str::limit((string) $program->title, 80, ''));

        return 'opp-'.($slug !== '' ? $slug : $program->getKey());
    }

    public static function url(Specialty $specialty, Program $program): string
    {
        return LocalizedUrl::route('specialties.show', $specialty).'#'.self::anchor($program);
    }

    /**
     * Додає id програм до відповідних заголовків h2–h4 опису.
     *
     * @return array{0: string, 1: list<int>} HTML і ID програм, якорі яких знайдено в заголовках
     */
    public static function apply(Specialty $specialty, ?string $html): array
    {
        $html = (string) $html;
        if ($html === '' || $specialty->programs->isEmpty()) {
            return [$html, []];
        }

        preg_match_all('~<h([2-4])\b([^>]*)>(.*?)</h\1>~isu', $html, $headings, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        $texts = array_map(fn ($h) => self::normalize(strip_tags($h[3][0])), $headings);

        // Довші назви першими — щоб коротка назва не забрала заголовок довшої, що її містить
        $programs = $specialty->programs->sortByDesc(fn (Program $p) => mb_strlen(self::normalize($p->localized('title'))));

        $claimed = [];
        foreach ($programs as $program) {
            $title = self::normalize($program->localized('title'));
            if ($title === '') {
                continue;
            }
            foreach ($texts as $i => $text) {
                if (! isset($claimed[$i]) && str_contains($text, $title)) {
                    $claimed[$i] = $program;
                    break;
                }
            }
        }

        // Заміни з кінця, щоб зсуви ще не оброблених заголовків лишались чинними
        krsort($claimed);
        foreach ($claimed as $i => $program) {
            [$whole, $offset] = $headings[$i][0];
            $attributes = preg_replace('~\s+id\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)~i', '', $headings[$i][2][0]);
            $tag = '<h'.$headings[$i][1][0].' id="'.e(self::anchor($program)).'"'.$attributes.'>'.$headings[$i][3][0].'</h'.$headings[$i][1][0].'>';
            $html = substr_replace($html, $tag, $offset, strlen($whole));
        }

        return [$html, array_values(array_map(fn (Program $p) => $p->getKey(), $claimed))];
    }

    private static function normalize(?string $text): string
    {
        $text = html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(['’', 'ʼ', '`', '‘', '´'], "'", $text);
        $text = str_replace(['«', '»', '"', '“', '”', '„'], ' ', $text);

        return trim(preg_replace('~\s+~u', ' ', mb_strtolower($text)));
    }
}
