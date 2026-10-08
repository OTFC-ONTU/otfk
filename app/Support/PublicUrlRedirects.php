<?php

namespace App\Support;

use App\Models\DocumentCategory;
use App\Models\LegacyRedirect;
use App\Models\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Перенаправлення для адрес матеріалів, що змінилися на самому сайті (KeepsPublicUrls):
 * зміна slug опублікованого матеріалу → 301 зі старої адреси на нову; видалення → 301 на
 * батьківський розділ чи список. Записи йдуть у ту саму карту legacy_redirects (діє лише на 404,
 * живі сторінки не перекриваються), для обох мов (/… і /en/…), одним переходом: наявні
 * перенаправлення на стару адресу переспрямовуються, автоматичний запис зі старою адресою як
 * джерелом оновлюється (ручні записи адміністратора — ні), запис, джерелом якого є нова (тепер жива)
 * адреса, вимикається, а ціль, що сама переадресовує, замінюється кінцевою. Помилка карти не
 * зупиняє збереження матеріалу (лише журнал).
 */
class PublicUrlRedirects
{
    private const LOCALES = ['', '/en'];

    /** Примітка автоматичних записів (за нею їх відрізняють від ручних). */
    public const AUTO_NOTE = 'Автоматично';

    /** Адреса матеріалу змінилася: $from → $to (відносні шляхи без мови). */
    public static function moved(string $from, string $to, string $note): void
    {
        if ($from === $to) {
            return;
        }
        foreach (self::LOCALES as $prefix) {
            self::point(self::localized($prefix, $from), self::localized($prefix, $to), $note);
        }
    }

    /** Матеріал видалено: його адреса веде на $fallback (батьківський розділ, список). */
    public static function removed(string $path, string $fallback, string $note): void
    {
        foreach (self::LOCALES as $prefix) {
            self::point(self::localized($prefix, $path), self::localized($prefix, $fallback), $note);
        }
    }

    /** Кількість активних перенаправлень, що ведуть на адресу (для попередження перед видаленням). */
    public static function incoming(string $path): int
    {
        return self::incomingQuery($path)->count();
    }

    private static function point(string $from, string $to, string $note): void
    {
        try {
            $to = self::finalTarget($to);
            [$toPath] = LegacyRedirects::normalize((string) parse_url($to, PHP_URL_PATH));

            // Нова адреса тепер жива: запис, що перенаправляв з неї, створив би ланцюжок
            LegacyRedirect::query()->where('is_active', true)->where('source_hash', LegacyRedirects::hash($toPath, null))
                ->get()->each(function (LegacyRedirect $redirect) use ($note): void {
                    $redirect->is_active = false;
                    $redirect->note = self::note($redirect->note, $note.' — вимкнено: адреса знову жива');
                    $redirect->save();
                });

            // Наявні перенаправлення на стару адресу (за нормалізованим шляхом, як перевіряє модель) —
            // одразу на нову, зі збереженням власних параметрів і #якоря
            self::incomingQuery($from)->get()->each(function (LegacyRedirect $redirect) use ($to, $note): void {
                $query = parse_url((string) $redirect->target_url, PHP_URL_QUERY);
                $fragment = parse_url((string) $redirect->target_url, PHP_URL_FRAGMENT) ?: parse_url($to, PHP_URL_FRAGMENT);
                $redirect->note = self::note($redirect->note, $note.' — було: '.$redirect->target_url);
                $redirect->target_url = strtok($to, '#').($query ? '?'.$query : '').($fragment ? '#'.$fragment : '');
                $redirect->save();
            });

            $existing = LegacyRedirect::query()->where('source_hash', LegacyRedirects::hash(...LegacyRedirects::normalize($from)))->first();
            // Ручний запис адміністратора не перезаписуємо: якщо його вимкнули, коли адреса ожила, — повертаємо як був
            if ($existing && ! self::isAutomatic($existing)) {
                if (! $existing->is_active) {
                    $existing->is_active = true;
                    $existing->note = self::note($existing->note, $note.' — увімкнено знову');
                    $existing->save();
                }

                return;
            }

            ($existing ?? new LegacyRedirect(['source_path' => $from]))->fill([
                'action' => LegacyRedirect::REDIRECT,
                'target_url' => $to,
                'status_code' => 301,
                'is_active' => true,
                'note' => self::note($existing?->note, $note),
            ])->save();
        } catch (Throwable $e) {
            Log::warning('Public URL redirect skipped', ['from' => $from, 'to' => $to, 'error' => $e->getMessage()]);
        }
    }

    /** Активні записи, ціль яких — ця адреса (будь-який запис шляху, з якорем чи параметрами). */
    private static function incomingQuery(string $path): Builder
    {
        [$normalized] = LegacyRedirects::normalize($path);

        return LegacyRedirect::query()->where('is_active', true)->where('target_path_hash', LegacyRedirects::hash($normalized, null));
    }

    /**
     * Ціль, що сама переадресовує (сторінка розділу документів, стара сторінка «Викладачі комісії»), —
     * одразу кінцева адреса, щоб старе посилання давало один перехід.
     */
    private static function finalTarget(string $to): string
    {
        $prefix = str_starts_with($to, '/en/') ? '/en' : '';
        $slug = trim(substr($to, strlen($prefix)), '/');
        if ($slug === '' || str_contains($slug, '/') || ! ($page = Page::query()->published()->where('slug', $slug)->first())) {
            return $to;
        }
        if ($category = DocumentCategory::query()->where('page_id', $page->getKey())->first()) {
            return $prefix.route('documents.category', $category->slug, false);
        }
        if ($department = DepartmentStaffAnchor::departmentFor($page)) {
            return $prefix.DepartmentStaffAnchor::path($department);
        }

        return $to;
    }

    /** Записи, створені цим механізмом (примітка починається з «Автоматично»). */
    private static function isAutomatic(LegacyRedirect $redirect): bool
    {
        return str_starts_with((string) $redirect->note, self::AUTO_NOTE);
    }

    private static function localized(string $prefix, string $path): string
    {
        return $prefix === '' ? $path : rtrim($prefix.$path, '/');
    }

    private static function note(?string $existing, string $note): string
    {
        return mb_substr(trim(($existing ? $existing.'; ' : '').$note), 0, 500);
    }
}
