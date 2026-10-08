<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Політика індексації та canonical-адрес (docs/seo-plan.md, етапи 1 і 4).
 *
 * Індексація дозволена лише на основному домені: тестовий хостинг і локальні
 * копії отримують `noindex` для кожної відповіді, але robots.txt не забороняє
 * обхід — інакше робот не побачить директиву. `/en` індексується посторінково:
 * розділи з перекладеним каркасом (ENGLISH_LISTINGS) і матеріали з повним
 * опублікованим незастарілим перекладом (translation()).
 */
class Seo
{
    /**
     * Значущі параметри запиту, які лишаються в canonical. Решта (utm_*,
     * fbclid, gclid, q тощо) відкидається. `page=1` відкидається окремо.
     */
    public const CANONICAL_QUERY = ['page', 'category', 'year'];

    /**
     * Розділи, які на `/en` індексуються без окремого матеріалу: їхній каркас
     * перекладено словниками. Сторінки матеріалів (новина, CMS-сторінка,
     * спеціальність…) позначаються через translation(); непозначені адреси `/en`
     * поза переліком (RSS, ICS, підказки, пошук) лишаються noindex.
     */
    public const ENGLISH_LISTINGS = [
        'home', 'news.index', 'specialties.index', 'structure.index', 'documents.index',
        'galleries.index', 'video.index', 'events', 'faq', 'staff.administration',
        'bells', 'quiz', 'contacts',
    ];

    /** Атрибут запиту зі станом англійського перекладу головного матеріалу сторінки. */
    private const ENGLISH_STATE = 'seo.english_indexable';

    /** Чи може пошуковик індексувати цю відповідь (без урахування `/en` та адмінки). */
    public static function indexable(?Request $request = null): bool
    {
        $mode = strtolower((string) config('otfk.seo.indexing', 'auto'));

        if (in_array($mode, ['true', '1', 'on', 'yes'], true)) {
            return true;
        }
        if (in_array($mode, ['false', '0', 'off', 'no'], true)) {
            return false;
        }

        // auto: індексуємо лише відповіді основного домену.
        $host = strtolower(($request ?? request())->getHost());

        return $host !== '' && $host === strtolower((string) config('otfk.seo.primary_host'));
    }

    /**
     * Canonical поточної сторінки: шлях без слешу в кінці плюс лише значущі
     * параметри у сталому порядку. Друга сторінка новин посилається сама на себе.
     */
    public static function canonical(?Request $request = null): string
    {
        $request ??= request();

        $query = [];
        foreach (self::CANONICAL_QUERY as $key) {
            $value = $request->query($key);
            if (! is_string($value) || $value === '') {
                continue;
            }
            if ($key === 'page' && (! ctype_digit($value) || (int) $value <= 1)) {
                continue;
            }
            $query[$key] = $key === 'page' ? (string) (int) $value : $value;
        }

        $url = $request->url();

        return $query === [] ? $url : $url.'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Позначає головний матеріал сторінки: `/en` індексується лише з повним
     * опублікованим і незастарілим перекладом. Позначка спільна для обох мов —
     * українська сторінка за нею вирішує, чи виводити hreflang.
     */
    public static function translation(?Model $model, ?Request $request = null): void
    {
        ($request ?? request())->attributes->set(
            self::ENGLISH_STATE,
            $model !== null && method_exists($model, 'hasIndexableEnglishTranslation') && $model->hasIndexableEnglishTranslation(),
        );
    }

    /** Чи має сторінка індексовану англійську версію (без урахування домену). */
    public static function englishIndexable(?Request $request = null): bool
    {
        $request ??= request();

        if ($request->attributes->has(self::ENGLISH_STATE)) {
            return (bool) $request->attributes->get(self::ENGLISH_STATE);
        }

        $name = (string) $request->route()?->getName();
        $name = str_starts_with($name, 'en.') ? substr($name, 3) : $name;

        return in_array($name, self::ENGLISH_LISTINGS, true);
    }

    public static function isEnglish(?Request $request = null): bool
    {
        return ($request ?? request())->segment(1) === 'en';
    }

    public static function isNoindex(?string $robots): bool
    {
        return $robots !== null && str_contains(strtolower($robots), 'noindex');
    }

    /**
     * Підсумкова директива meta robots: тестовий домен закритий повністю,
     * власна директива сторінки зберігається, `/en` без індексованого
     * перекладу — `noindex, follow`. null — індексувати.
     */
    public static function robots(?string $robots = null, ?Request $request = null): ?string
    {
        $request ??= request();

        if (! self::indexable($request)) {
            return 'noindex, nofollow';
        }
        if (self::isNoindex($robots)) {
            return $robots;
        }
        if (self::isEnglish($request) && ! self::englishIndexable($request)) {
            return 'noindex, follow';
        }

        return $robots;
    }

    /**
     * hreflang-пари лише тоді, коли індексуються обидві мовні версії сторінки.
     * Набір однаковий на обох версіях, x-default — українська адреса; значущі
     * параметри canonical (page, category) зберігаються.
     *
     * @return array<string, string> hreflang => абсолютна адреса
     */
    public static function alternates(?string $robots = null, ?Request $request = null): array
    {
        $request ??= request();

        if (! self::indexable($request) || self::isNoindex($robots) || ! self::englishIndexable($request)) {
            return [];
        }

        $canonical = self::canonical($request);
        $uk = LocalizedUrl::to($canonical, 'uk');
        $en = LocalizedUrl::to($canonical, 'en');

        return $uk === $en ? [] : ['uk' => $uk, 'en' => $en, 'x-default' => $uk];
    }
}
