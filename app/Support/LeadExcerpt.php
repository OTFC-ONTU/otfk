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
        $lead = MetaText::clean($excerpt);

        return $lead !== '' && Str::startsWith(MetaText::clean($body), Str::limit($lead, 60, ''));
    }
}
