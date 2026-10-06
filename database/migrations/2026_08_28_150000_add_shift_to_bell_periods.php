<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Дві зміни в розкладі дзвінків: колонка `shift` + перемикач
     * `bells_second_shift` (друга зміна показується/ховається без видалення пар).
     * Заразом типовий розклад із міграції 2026_06_10_180000 замінюється на
     * реальний (зі стенда коледжу) — але лише якщо часи ще не правили в адмінці.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('bell_periods', 'shift')) {
            Schema::table('bell_periods', function (Blueprint $table) {
                $table->unsignedTinyInteger('shift')->default(1)->after('number');
            });
        }

        if ($this->scheduleIsUntouched()) {
            DB::table('bell_periods')->delete();

            foreach ($this->realSchedule() as [$shift, $number, $starts, $ends]) {
                DB::table('bell_periods')->insert([
                    'shift' => $shift, 'number' => $number, 'starts' => $starts, 'ends' => $ends,
                    'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        if (! DB::table('settings')->where('key', 'bells_second_shift')->exists()) {
            DB::table('settings')->insert([
                'key' => 'bells_second_shift', 'value' => '1', 'group' => 'general', 'type' => 'text',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        Cache::forget('settings.map');
        Cache::forget('settings.translations');
        Cache::forget('bell_periods.v3');
        Cache::forget('bell_periods');
    }

    public function down(): void
    {
        Schema::table('bell_periods', fn (Blueprint $table) => $table->dropColumn('shift'));
        DB::table('settings')->where('key', 'bells_second_shift')->delete();

        Cache::forget('settings.map');
        Cache::forget('bell_periods');
    }

    /** Реальний розклад: 1 зміна (4 пари) + 2 зміна (4 пари), пара — 70 хвилин. */
    private function realSchedule(): array
    {
        return [
            [1, 1, '08:30', '09:40'],
            [1, 2, '09:50', '11:00'],
            [1, 3, '11:30', '12:40'],
            [1, 4, '12:50', '14:00'],
            [2, 1, '13:00', '14:10'],
            [2, 2, '14:20', '15:30'],
            [2, 3, '15:40', '16:50'],
            [2, 4, '17:00', '18:10'],
        ];
    }

    /** Чи стоїть у базі саме типовий розклад із першої міграції (тоді його можна переписати). */
    private function scheduleIsUntouched(): bool
    {
        $seeded = ['1 08:30 09:50', '2 10:00 11:20', '3 11:50 13:10', '4 13:20 14:40', '5 14:50 16:10', '6 16:20 17:40'];

        $current = DB::table('bell_periods')->orderBy('number')->get(['number', 'starts', 'ends'])
            ->map(fn ($r) => $r->number.' '.substr((string) $r->starts, 0, 5).' '.substr((string) $r->ends, 0, 5))
            ->all();

        return $current === $seeded;
    }
};
