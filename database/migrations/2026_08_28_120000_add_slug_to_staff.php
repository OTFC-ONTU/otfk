<?php

use App\Models\Staff;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Слаг персоналії — для персональних сторінок викладачів (/personal/{slug}).
     */
    public function up(): void
    {
        if (! Schema::hasColumn('staff', 'slug')) {
            Schema::table('staff', function (Blueprint $table) {
                $table->string('slug')->nullable()->unique()->after('full_name');
            });
        }

        // Бекфіл наявних записів (ідемпотентно: заповнюємо лише порожні слаги).
        Staff::query()->whereNull('slug')->orderBy('id')->each(function (Staff $person) {
            $person->slug = Staff::uniqueSlug($person->full_name, $person->id);
            $person->saveQuietly();
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
