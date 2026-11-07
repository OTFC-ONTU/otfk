<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Короткий опис матеріалу поруч із його тілом: один текст не показуємо двічі поспіль.
 */
class LeadExcerpt
{
    /**
     * Чи тіло вже починається з короткого опису (порожній опис нічого не повторює).
     * Опис часто є обрізаним початком тіла (автогенерація при імпорті) або скопійованим абзацом.
     */
    public static function repeatsBody(?string $excerpt, ?string $body): bool
    {
        $lead = self::plain($excerpt);

        return $lead !== '' && Str::startsWith(self::plain($body), Str::limit($lead, 60, ''));
    }

    private static function plain(?string $html): string
    {
        // Санітайзер кодує символи (наприклад, апостроф), тож порівнюємо вже декодований текст.
        return Str::squish(html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
