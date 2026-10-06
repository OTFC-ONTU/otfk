<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Прапорець «ключова сторінка розділу»: на сторінці-хабі (Абітурієнту, Студенту,
     * Про коледж) такі дочірні сторінки виносяться нагору окремими великими картками,
     * решта лишається в загальному списку. Керується перемикачем в адмінці.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('pages', 'is_featured')) {
            Schema::table('pages', function (Blueprint $table) {
                $table->boolean('is_featured')->default(false)->after('is_heritage');
            });
        }

        // Стартовий набір для розділу «Абітурієнту» — лише якщо такі сторінки існують.
        DB::table('pages')
            ->whereIn('slug', $this->applicantHighlights())
            ->update(['is_featured' => true]);
    }

    public function down(): void
    {
        Schema::table('pages', fn (Blueprint $table) => $table->dropColumn('is_featured'));
    }

    /** @return list<string> */
    private function applicantHighlights(): array
    {
        return [
            'zayavka',
            'pravyla-pryyomu',
            'informaciia-pro-specialnosti',
            'kviz',
        ];
    }
};
