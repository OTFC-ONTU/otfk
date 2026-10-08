<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CMS-сторінка розділу публічної інформації: повний вміст розділу оригіналу
     * (текст, таблиці, зображення, файли). Якщо задана й опублікована — розділ показує її.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('document_categories', 'page_id')) {
            Schema::table('document_categories', function (Blueprint $table) {
                $table->foreignId('page_id')->nullable()->constrained('pages')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('document_categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('page_id');
        });
    }
};
