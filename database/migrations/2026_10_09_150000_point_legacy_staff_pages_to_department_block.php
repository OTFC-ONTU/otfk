<?php

use App\Models\LegacyRedirect;
use App\Models\Page;
use App\Support\DepartmentStaffAnchor;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Старі адреси «/structure/cycles_commissions/…/personel» у карті вели на сторінки
 * «Викладачі комісії …» (/vikladaci-komisiyi-…), які тепер самі переадресовують на блок
 * викладачів комісії (DepartmentStaffAnchor). Щоб старий адрес давав один перехід, запис карти
 * веде одразу на /struktura/{комісія}#vykladachi; колишнє призначення дописується в note.
 * Через модель (нормалізація, хеші, перевірка циклів/ланцюжків, скидання кешу карти).
 * Ідемпотентно: записи, що вже ведуть на комісію, не змінюються; незнайдена комісія — без змін.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('legacy_redirects') || ! Schema::hasTable('pages')) {
            return;
        }

        $departments = DepartmentStaffAnchor::pageDepartments();
        if ($departments === []) {
            return;
        }
        $slugs = Page::query()->whereKey(array_keys($departments))->pluck('slug', 'id');

        foreach ($slugs as $pageId => $slug) {
            $target = DepartmentStaffAnchor::path($departments[$pageId]);

            LegacyRedirect::query()->where('target_url', '/'.$slug)->get()->each(function (LegacyRedirect $redirect) use ($target, $slug): void {
                try {
                    $redirect->note = trim(($redirect->note ? $redirect->note.'; ' : '').'було: /'.$slug);
                    $redirect->target_url = $target;
                    $redirect->save();
                } catch (Throwable $e) {
                    Log::warning('Legacy redirect retarget skipped', ['id' => $redirect->id, 'error' => $e->getMessage()]);
                }
            });
        }
    }

    public function down(): void
    {
        // Попереднє призначення збережено в note; сторінки самі переадресовують — повертати не потрібно.
    }
};
