<?php

namespace App\Support;

use App\Models\Department;
use App\Models\Page;

/**
 * Якір блоку «Викладацький склад» на сторінці підрозділу (/struktura/{slug}#vykladachi).
 *
 * Імпортовані описи циклових комісій посилаються на окремі старі сторінки
 * «Викладачі комісії …» (/vikladaci-komisiyi-…), хоча нижче на тій самій сторінці вже є картки
 * викладачів (біографії й фото перенесено в профілі). Якщо картки є:
 * - посилання опису ведуть на якір блоку (лише при виведенні, HTML у БД не змінюється);
 * - сама стара сторінка має одну адресу — 301 на блок комісії (PageController), без sitemap,
 *   а карта старих адрес веде на комісію одразу (LegacyMapBuilder, міграція 2026_10_09_150000).
 * Зв'язок «сторінка → комісія» — за посиланням в описі комісії; дві сторінки колишніх комісій,
 * що увійшли до «економіки та товарознавства», — за MERGED_PAGES. Сторінка і текст у БД лишаються.
 * У редакторі на блок можна послатися адресою «#vykladachi».
 */
class DepartmentStaffAnchor
{
    public const ID = 'vykladachi';

    public const PAGE_PREFIX = 'vikladaci-komisiyi-';

    /** Сторінки колишніх комісій без посилання з опису чинної комісії: slug сторінки => slug комісії. */
    private const MERGED_PAGES = [
        'vikladaci-komisiyi-ekonomicnix-disciplin' => 'komisiia-ekonomiki-ta-tovaroznavstva',
        'vikladaci-komisiyi-tovaroznavstva' => 'komisiia-ekonomiki-ta-tovaroznavstva',
    ];

    public static function links(?string $html): string
    {
        return (string) preg_replace(
            '~(<a\b[^>]*\shref\s*=\s*)(["\'])(?:https?://[^/"\']+)?/(?:en/)?'.self::PAGE_PREFIX.'[^"\'#?]*(?:[?#][^"\']*)?\2~i',
            '$1$2#'.self::ID.'$2',
            (string) $html,
        );
    }

    /** Комісія, на блок викладачів якої замінено сторінку (лише сторінки з префіксом PAGE_PREFIX). */
    public static function departmentFor(Page $page): ?Department
    {
        return str_starts_with((string) $page->slug, self::PAGE_PREFIX) ? (self::pageDepartments()[$page->getKey()] ?? null) : null;
    }

    /** Публічна адреса блоку (з мовою інтерфейсу). */
    public static function url(Department $department): string
    {
        return LocalizedUrl::route('structure.show', $department).'#'.self::ID;
    }

    /** Відносна адреса блоку — для карти старих адрес. */
    public static function path(Department $department): string
    {
        return route('structure.show', $department->slug, false).'#'.self::ID;
    }

    /** @return array<int, Department> id сторінки => опублікована комісія з опублікованими картками */
    public static function pageDepartments(): array
    {
        $pages = Page::query()->where('slug', 'like', self::PAGE_PREFIX.'%')->pluck('slug', 'id');
        if ($pages->isEmpty()) {
            return [];
        }

        $departments = Department::query()->published()
            ->whereHas('staff', fn ($query) => $query->where('is_published', true))
            ->get(['id', 'slug', 'title', 'description']);

        $result = [];
        foreach ($pages as $id => $slug) {
            $department = $departments->first(fn (Department $department) => preg_match(
                '~\shref\s*=\s*(["\'])(?:https?://[^/"\']+)?/(?:en/)?'.preg_quote($slug, '~').'(?:[?#][^"\']*)?\1~i',
                (string) $department->description,
            ) === 1) ?? $departments->firstWhere('slug', self::MERGED_PAGES[$slug] ?? null);

            if ($department) {
                $result[$id] = $department;
            }
        }

        return $result;
    }
}
