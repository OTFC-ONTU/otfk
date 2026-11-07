<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Кнопки-посилання на сторінках розділів (quick_links, location=page_link):
 * page_id — сторінка, на якій показується кнопка. Адреса розширюється до 2048 символів,
 * бо посилання на Google Forms та подібні сервіси можуть не вміщуватися у 255.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('quick_links', 'page_id')) {
            Schema::table('quick_links', function (Blueprint $table) {
                $table->foreignId('page_id')->nullable()->after('location')->constrained('pages')->nullOnDelete();
            });
        }

        Schema::table('quick_links', function (Blueprint $table) {
            $table->string('url', 2048)->default('#')->change();
        });
    }

    public function down(): void
    {
        // Кнопки без page_id не мають сенсу: без колонки вони стали б рядками невідомого призначення
        DB::table('quick_links')->where('location', 'page_link')->delete();

        Schema::table('quick_links', function (Blueprint $table) {
            $table->dropConstrainedForeignId('page_id');
        });

        // Довші адреси інших записів не обрізаємо — лише тоді повертаємо стару довжину
        if (! DB::table('quick_links')->whereRaw('LENGTH(url) > 255')->exists()) {
            Schema::table('quick_links', function (Blueprint $table) {
                $table->string('url')->default('#')->change();
            });
        }
    }
};
