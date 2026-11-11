<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Лічильники «Скриньки довіри» по місяцях (Київ): лише числа, без текстів,
 * часу й даних відправника — сторінка налаштувань показує їх за поточний місяць.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('trust_box_stats')) {
            Schema::create('trust_box_stats', function (Blueprint $table) {
                $table->string('month', 7)->primary(); // 2026-11
                $table->unsignedInteger('sent')->default(0);
                $table->unsignedInteger('rejected')->default(0);
                $table->unsignedInteger('failed')->default(0);
                $table->unsignedInteger('captcha_unavailable')->default(0);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('trust_box_stats');
    }
};
