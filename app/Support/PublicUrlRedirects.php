<?php

namespace App\Support;

use App\Models\LegacyRedirect;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Перенаправлення для адрес матеріалів, що змінилися на самому сайті (KeepsPublicUrls):
 * зміна slug опублікованого матеріалу → 301 зі старої адреси на нову; видалення → 301 на
 * батьківський розділ чи список. Записи йдуть у ту саму карту legacy_redirects (діє лише на 404,
 * живі сторінки не перекриваються), для обох мов (/… і /en/…), одним переходом: наявні
 * перенаправлення на стару адресу переспрямовуються, запис зі старою адресою як джерелом
 * оновлюється, а запис, джерелом якого є нова (тепер жива) адреса, вимикається — інакше
 * вийшов би ланцюжок. Помилка карти не зупиняє збереження матеріалу (лише журнал).
 */
class PublicUrlRedirects
{
    private const LOCALES = ['', '/en'];

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
        return LegacyRedirect::query()->where('is_active', true)
            ->where(fn ($query) => self::targeting($query, $path))
            ->count();
    }

    private static function point(string $from, string $to, string $note): void
    {
        try {
            // Нова адреса тепер жива: запис, що перенаправляв з неї, створив би ланцюжок
            LegacyRedirect::query()->where('is_active', true)->where('source_hash', LegacyRedirects::hash(...LegacyRedirects::normalize($to)))
                ->get()->each(function (LegacyRedirect $redirect) use ($note): void {
                    $redirect->is_active = false;
                    $redirect->note = self::note($redirect->note, $note.' — адреса знову жива');
                    $redirect->save();
                });

            // Наявні перенаправлення на стару адресу — одразу на нову (зі збереженням #якоря)
            LegacyRedirect::query()->where('is_active', true)->where(fn ($query) => self::targeting($query, $from))
                ->get()->each(function (LegacyRedirect $redirect) use ($from, $to, $note): void {
                    $suffix = (string) substr((string) $redirect->target_url, strlen($from));
                    $redirect->target_url = $to.$suffix;
                    $redirect->note = self::note($redirect->note, $note.' — було: '.$from.$suffix);
                    $redirect->save();
                });

            $redirect = LegacyRedirect::query()->where('source_hash', LegacyRedirects::hash(...LegacyRedirects::normalize($from)))->first()
                ?? new LegacyRedirect(['source_path' => $from]);
            $redirect->fill([
                'action' => LegacyRedirect::REDIRECT,
                'target_url' => $to,
                'status_code' => 301,
                'is_active' => true,
                'note' => self::note($redirect->exists ? $redirect->note : null, $note),
            ])->save();
        } catch (Throwable $e) {
            Log::warning('Public URL redirect skipped', ['from' => $from, 'to' => $to, 'error' => $e->getMessage()]);
        }
    }

    /** Ціль — саме ця адреса (з якорем чи параметрами або без). */
    private static function targeting($query, string $path): void
    {
        $query->where('target_url', $path)
            ->orWhere('target_url', 'like', addcslashes($path, '%_\\').'#%')
            ->orWhere('target_url', 'like', addcslashes($path, '%_\\').'?%');
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
