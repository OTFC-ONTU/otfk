<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Порядок в адмінці тепер змінюється перетягуванням (ReordersBySwappingPositions), а воно міняє
 * місцями наявні номери sort_order. Однакові номери (імпорт, типове 0) дали б випадковий порядок
 * при першому перетягуванні, тому кожна таблиця з повторами нумерується 1…n рівно в тому порядку,
 * який зараз показує сайт (sort_order + ті самі додаткові поля сортування, що в моделях).
 * Глобальна нумерація зберігає й порядок усередині груп (розділ, підрозділ, категорія).
 * Ідемпотентно: таблиця без повторів не змінюється; без подій моделей і без зміни updated_at.
 */
return new class extends Migration
{
    /** Таблиця => додаткові поля сортування після sort_order (як scopeOrdered/зв'язки моделей). */
    private const ORDERS = [
        'banners' => [['id', 'desc']],
        'videos' => [['published_at', 'desc'], ['id', 'asc']],
        'galleries' => [['published_at', 'desc'], ['id', 'asc']],
        'specialties' => [['title', 'asc'], ['id', 'asc']],
        'document_categories' => [['title', 'asc'], ['id', 'asc']],
        'departments' => [['title', 'asc'], ['id', 'asc']],
        'quick_links' => [['id', 'asc']],
        'pages' => [['title', 'asc'], ['id', 'asc']],
        'staff' => [['full_name', 'asc'], ['id', 'asc']],
        'menu_items' => [['id', 'asc']],
        'faqs' => [['id', 'asc']],
        'stat_items' => [['id', 'asc']],
        'news_categories' => [['id', 'asc']],
        'programs' => [['id', 'asc']],
        'documents' => [['published_at', 'desc'], ['id', 'asc']],
        'quiz_questions' => [['id', 'asc']],
    ];

    public function up(): void
    {
        foreach (self::ORDERS as $table => $orders) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'sort_order')) {
                continue;
            }

            $hasDuplicates = DB::table($table)->select('sort_order')->groupBy('sort_order')->havingRaw('COUNT(*) > 1')->exists();
            if (! $hasDuplicates) {
                continue;
            }

            $query = DB::table($table)->orderBy('sort_order');
            foreach ($orders as [$column, $direction]) {
                if (Schema::hasColumn($table, $column)) {
                    $query->orderBy($column, $direction);
                }
            }

            DB::transaction(function () use ($query, $table): void {
                $position = 0;
                foreach ($query->pluck('id') as $id) {
                    DB::table($table)->where('id', $id)->update(['sort_order' => ++$position]);
                }
            });
        }

        Cache::forget('menu.navigation');
        Cache::forget('sitemap.entries');
    }

    public function down(): void
    {
        // Нумерація відповідає порядку на сайті — повертати повтори немає сенсу.
    }
};
