<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Назви документів оригіналу (звіти опитувань) довші за 255 символів: title і title_en — TEXT.
     * Дані не змінюються; повтор після часткового виконання безпечний.
     */
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->text('title')->change();
            if (Schema::hasColumn('documents', 'title_en')) {
                $table->text('title_en')->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        // Зворотне звуження обрізало б довгі назви — схема лишається TEXT.
    }
};
