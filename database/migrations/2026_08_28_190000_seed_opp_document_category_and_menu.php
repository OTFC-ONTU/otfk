<?php

use App\Models\DocumentCategory;
use App\Models\MenuItem;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Освітньо-професійні програми: окрема категорія документів + власне посилання в меню.
     *
     * На старому сайті /applicant/educational_and_professional_programs — розділ
     * із ~62 PDF (ОПП і навчальні плани за роками). Після редизайну пункт меню
     * «Освітньо-професійні програми» тимчасово вів на /spetsialnosti (дублюючи
     * «Наші спеціальності»). Створюємо категорію документів і перенаправляємо
     * пункт меню на неї; самі PDF завантажує otfk:import-docs (docMap).
     */
    public function up(): void
    {

        DocumentCategory::firstOrCreate(
            ['slug' => 'osvitno-profesiyni-prohramy'],
            [
                'title' => 'Освітньо-професійні програми',
                'sort_order' => (int) DocumentCategory::max('sort_order') + 1,
            ]
        );

        // Через Eloquent, щоб скинувся кеш menu.navigation (booted::saved).
        MenuItem::where('label', 'Освітньо-професійні програми')
            ->where('url', '/spetsialnosti')
            ->get()
            ->each(fn (MenuItem $item) => $item->update(['url' => '/dokumenty/osvitno-profesiyni-prohramy']));
    }

    public function down(): void
    {
        // Контент (категорію з документами) не видаляємо — повертаємо лише меню.
        MenuItem::where('label', 'Освітньо-професійні програми')
            ->where('url', '/dokumenty/osvitno-profesiyni-prohramy')
            ->get()
            ->each(fn (MenuItem $item) => $item->update(['url' => '/spetsialnosti']));
    }
};
