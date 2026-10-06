<?php

use App\Models\Program;
use App\Models\Specialty;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Чистка демо-залишків SiteSeeder після імпорту реальних спеціальностей
     * (2026_08_28_210000_import_specialties_content):
     *
     * 1. «Облік і оподаткування» (071) — демо-запис сидера: на старому сайті
     *    (applicant/our_specialties, дзеркало site-audit/2026-08-28) такої
     *    спеціальності немає взагалі. Видаляємо разом з ОПП, але лише поки
     *    запис досі демо (числовий код + шаблонний опис сидера).
     * 2. Демо-описи «Сучасна підготовка фахівців за спеціальністю…» у F2/F7/G13 —
     *    імпорт їх пропустив (замінював лише заглушку «наповнюється»), тепер
     *    ставимо реальні описи. Оновлюємо тільки поки там демо-текст.
     * 3. sort_order — у порядку розділів старого сайту: F7, F2, D2, D3, D5,
     *    D7, G4, G13, G15.
     *
     * down() контент не повертає (конвенція проєкту).
     */
    public function up(): void
    {
        // Жовтневе наповнення вже актуалізовано; історичні фікстури його не перезаписують.
        if (\Illuminate\Support\Facades\Schema::hasColumn('pages', 'translation_published')) {
            return;
        }

        $demo = Specialty::where('slug', 'oblik-i-opodatkuvannya')
            ->where('code', '071')
            ->first();

        if ($demo && Str::startsWith((string) $demo->short_description, 'Сучасна підготовка фахівців')) {
            // «Фінансові» варіанти квіза були привʼязані до 071 — перекидаємо на
            // D2 «Фінанси…» (найближча реальна спеціальність), інакше FK set null
            // зробить їх нейтральними.
            $d2 = Specialty::where('code', 'D2')->first();
            if ($d2) {
                DB::table('quiz_options')->where('specialty_id', $demo->id)->update(['specialty_id' => $d2->id]);
            }

            Program::where('specialty_id', $demo->id)->delete();
            $demo->delete();
        }

        $shorts = [
            'inzheneriya-programnoho-zabezpechennya' => 'Проектування, розробка й тестування програмного забезпечення: від аналізу вимог до супроводу готового продукту.',
            'kompyuterna-inzheneriya' => 'Комп’ютерні системи і мережі, комп’ютерна графіка та web-дизайн, безпека комп’ютерних систем — три освітні програми в галузі інформаційних технологій.',
            'kharchovi-tekhnolohiyi' => 'Технології виробництва хліба, кондитерських і макаронних виробів та організація сучасного громадського харчування.',
        ];

        foreach ($shorts as $slug => $short) {
            Specialty::where('slug', $slug)
                ->where('short_description', 'like', 'Сучасна підготовка фахівців%')
                ->update(['short_description' => $short]);
        }

        $order = [
            'kompyuterna-inzheneriya',
            'inzheneriya-programnoho-zabezpechennya',
            'finansy-bankivska-sprava',
            'menedzhment',
            'marketynh',
            'torhivlya',
            'enerhovyrobnytstvo',
            'kharchovi-tekhnolohiyi',
            'tekhnolohiyi-lehkoyi-promyslovosti',
        ];

        foreach ($order as $i => $slug) {
            Specialty::where('slug', $slug)->update(['sort_order' => $i + 1]);
        }
    }

    public function down(): void
    {
        // Контент не відновлюємо — демо-запис і демо-описи поверненню не підлягають.
    }
};
