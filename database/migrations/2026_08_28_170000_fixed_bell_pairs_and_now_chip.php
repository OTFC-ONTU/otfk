<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Розклад дзвінків став екраном налаштувань замість CRUD: у кожній зміні
     * рівно 4 пари. Міграція прибирає зайві пари (номер > 4), добиває відсутні
     * типовими часами і додає перемикач плашки «зараз іде пара» (`bells_now_chip`).
     */
    public function up(): void
    {

        foreach ($this->defaults() as [$shift, $number, $starts, $ends]) {
            $exists = DB::table('bell_periods')->where('shift', $shift)->where('number', $number)->exists();

            if (! $exists) {
                DB::table('bell_periods')->insert([
                    'shift' => $shift, 'number' => $number, 'starts' => $starts, 'ends' => $ends,
                    'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        if (! DB::table('settings')->where('key', 'bells_now_chip')->exists()) {
            DB::table('settings')->insert([
                'key' => 'bells_now_chip', 'value' => '1', 'group' => 'general', 'type' => 'text',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        Cache::forget('settings.map');
        Cache::forget('settings.translations');
        Cache::forget('bell_periods.v3');
        Cache::forget('bell_periods.v2');
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'bells_now_chip')->delete();

        Cache::forget('settings.map');
        Cache::forget('bell_periods.v2');
    }

    /** Типові часи — ті самі, що на стенді коледжу (використовуються лише для відсутніх пар). */
    private function defaults(): array
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
};
