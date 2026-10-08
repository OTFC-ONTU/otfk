<?php

use App\Models\LegacyRedirect;
use App\Models\Page;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Імпортована сторінка «Інформація про спеціальності» дублювала розділ
 * /spetsialnosti старим текстом, а плитка хабу «Абітурієнту» вела саме на неї.
 * Сторінка стає плиткою-маршрутом (як kviz/faq): слаг spetsialnosti, без тіла,
 * тож плитка відкриває нову сторінку спеціальностей. Старі адреси (і /en) — 301 туди ж.
 * Ідемпотентно; down() контент не повертає.
 */
return new class extends Migration
{
    private const OLD_SLUG = 'informaciia-pro-specialnosti';

    private const TARGET = '/spetsialnosti';

    public function up(): void
    {
        $page = Page::query()->where('slug', self::OLD_SLUG)->first();
        if ($page && ! Page::query()->where('slug', 'spetsialnosti')->exists()) {
            $page->forceFill([
                'slug' => 'spetsialnosti',
                'body' => null,
                'body_en' => null,
                'excerpt' => $page->excerpt ?: 'Спеціальності коледжу, кваліфікації та освітньо-професійні програми.',
                'excerpt_en' => $page->excerpt_en ?: 'College specialties, qualifications and educational programmes.',
            ])->save();
        }

        // Нове встановлення старої сторінки не мало — редиректи не потрібні.
        if (! Page::query()->where('slug', 'spetsialnosti')->exists() || ! Schema::hasTable('legacy_redirects')) {
            return;
        }

        // Спершу перенаправити наявні записи на нову ціль, щоб не утворився ланцюжок.
        LegacyRedirect::query()->where('target_url', '/'.self::OLD_SLUG)->get()
            ->each(fn (LegacyRedirect $redirect) => $redirect->update(['target_url' => self::TARGET]));

        foreach (['' => self::TARGET, '/en' => '/en'.self::TARGET] as $prefix => $target) {
            $source = $prefix.'/'.self::OLD_SLUG;
            if (! LegacyRedirect::query()->where('source_path', $source)->whereNull('source_query')->exists()) {
                LegacyRedirect::create([
                    'source_path' => $source,
                    'action' => LegacyRedirect::REDIRECT,
                    'target_url' => $target,
                    'status_code' => 301,
                    'is_active' => true,
                    'note' => 'Стара сторінка «Інформація про спеціальності» замінена розділом /spetsialnosti',
                ]);
            }
        }
    }

    public function down(): void
    {
        // Контент старої сторінки не відновлюється.
    }
};
