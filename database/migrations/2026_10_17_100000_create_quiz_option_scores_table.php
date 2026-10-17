<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Квіз рахує бали освітньо-професійним програмам: один варіант відповіді може
     * додати різну кількість балів кільком ОПП. Стара прив’язка «варіант → одна
     * спеціальність» переноситься на всі ОПП цієї спеціальності з тими самими балами.
     */
    public function up(): void
    {
        if (! Schema::hasTable('quiz_option_scores')) {
            Schema::create('quiz_option_scores', function (Blueprint $table) {
                $table->id();
                $table->foreignId('quiz_option_id')->constrained('quiz_options')->cascadeOnDelete();
                $table->foreignId('program_id')->constrained('programs')->cascadeOnDelete();
                $table->unsignedTinyInteger('points')->default(1);
                $table->unique(['quiz_option_id', 'program_id']);
            });
        }

        if (! Schema::hasColumn('quiz_options', 'specialty_id')) {
            return;
        }

        // Спершу читаємо старі прив’язки і прибираємо колонки: SQLite перебудовує таблицю під час
        // видалення колонки, і каскад стер би вже перенесені бали.
        $legacy = DB::table('quiz_options')->whereNotNull('specialty_id')->get(['id', 'specialty_id', 'points']);

        Schema::table('quiz_options', function (Blueprint $table) {
            $table->dropConstrainedForeignId('specialty_id');
            $table->dropColumn('points');
        });

        $programs = DB::table('programs')->get(['id', 'specialty_id'])->groupBy('specialty_id');
        foreach ($legacy as $option) {
            foreach ($programs->get($option->specialty_id, collect()) as $program) {
                DB::table('quiz_option_scores')->insertOrIgnore([
                    'quiz_option_id' => $option->id,
                    'program_id' => $program->id,
                    'points' => max(1, (int) $option->points),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Найбільший внесок варіанта повертається як його єдина спеціальність. Бали читаються до зміни
        // quiz_options: на SQLite додавання колонки перебудовує таблицю і каскадом стерло б їх.
        $best = Schema::hasTable('quiz_option_scores')
            ? DB::table('quiz_option_scores')
                ->join('programs', 'programs.id', '=', 'quiz_option_scores.program_id')
                ->orderByDesc('quiz_option_scores.points')
                ->orderBy('quiz_option_scores.id')
                ->get(['quiz_option_scores.quiz_option_id', 'quiz_option_scores.points', 'programs.specialty_id'])
                ->unique('quiz_option_id')
            : collect();

        Schema::dropIfExists('quiz_option_scores');

        if (! Schema::hasColumn('quiz_options', 'specialty_id')) {
            Schema::table('quiz_options', function (Blueprint $table) {
                $table->foreignId('specialty_id')->nullable()->after('label')->constrained('specialties')->nullOnDelete();
                $table->unsignedTinyInteger('points')->default(1)->after('specialty_id');
            });
        }

        foreach ($best as $row) {
            DB::table('quiz_options')->where('id', $row->quiz_option_id)
                ->update(['specialty_id' => $row->specialty_id, 'points' => $row->points]);
        }
    }
};
