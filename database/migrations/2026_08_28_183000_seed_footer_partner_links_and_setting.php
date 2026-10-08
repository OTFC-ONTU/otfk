<?php

use App\Models\QuickLink;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Підвал за оригінальним сайтом (otfk.od.ua — джерело істини): крім соцмереж
     * там є посилання на офіційні ресурси. Заводимо їх як QuickLink з локацією
     * footer_partner (редагуються в адмінці: «Швидкі посилання» → «Партнер у підвалі»).
     * Додатково гарантуємо налаштування підвалу footer_about (текст «Про коледж») —
     * на оточеннях без сидера його могло не бути.
     */
    public function up(): void
    {
        // Жовтневе наповнення вже актуалізовано; історичні фікстури його не перезаписують.
        if (\Illuminate\Support\Facades\Schema::hasColumn('pages', 'translation_published')) {
            return;
        }

        $links = [
            // title, url, sort — порядок як у підвалі старого сайту
            ['ОНТУ', 'https://ontu.edu.ua', 1],
            ['МОН України', 'https://mon.gov.ua', 2],
            ['НМЦ ВФПО', 'https://nmc-vfpo.gov.ua', 3],
            ['Органічна платформа знань', 'https://organic-platform.org', 4],
            ['Урядова «гаряча лінія» 1545', 'https://ukc.gov.ua', 5],
        ];

        foreach ($links as [$title, $url, $sort]) {
            // firstOrCreate — не перетираємо ручні правки адміністратора
            QuickLink::firstOrCreate(
                ['location' => 'footer_partner', 'title' => $title],
                [
                    'url' => $url,
                    'open_new_tab' => true,
                    'sort_order' => $sort,
                    'is_visible' => true,
                ]
            );
        }

        if (! DB::table('settings')->where('key', 'footer_about')->exists()) {
            DB::table('settings')->insert([
                'key' => 'footer_about',
                'value' => 'Одеський технічний фаховий коледж - структурний підрозділ Одеського національного технологічного університету.',
                'group' => 'general',
                'type' => 'textarea',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            Cache::forget('settings.map');
        }
    }

    /** Контент не видаляємо — посилання й налаштування могли відредагувати вручну. */
    public function down(): void
    {
        //
    }
};
