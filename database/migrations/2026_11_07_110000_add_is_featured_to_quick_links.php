<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Прапорець «Вгорі» для кнопок-посилань (quick_links, location=page_link): на сторінці розділу
 * кнопка йде в блок «Ключові дії» над списком, на звичайній сторінці — над текстом.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('quick_links', 'is_featured')) {
            Schema::table('quick_links', function (Blueprint $table) {
                $table->boolean('is_featured')->default(false)->after('open_new_tab');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('quick_links', 'is_featured')) {
            Schema::table('quick_links', function (Blueprint $table) {
                $table->dropColumn('is_featured');
            });
        }
    }
};
