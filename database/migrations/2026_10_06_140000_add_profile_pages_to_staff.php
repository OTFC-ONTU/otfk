<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Сторінки викладача (як на оригіналі): «Результати професійної та наукової діяльності»
     * і «Відомості про підвищення кваліфікації». Картка персоналу веде на них.
     */
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            if (! Schema::hasColumn('staff', 'profile_page_id')) {
                $table->foreignId('profile_page_id')->nullable()->constrained('pages')->nullOnDelete();
            }
            if (! Schema::hasColumn('staff', 'qualification_page_id')) {
                $table->foreignId('qualification_page_id')->nullable()->constrained('pages')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropConstrainedForeignId('qualification_page_id');
            $table->dropConstrainedForeignId('profile_page_id');
        });
    }
};
