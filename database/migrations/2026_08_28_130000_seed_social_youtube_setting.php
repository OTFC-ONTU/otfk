<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Ключ social_youtube — посилання на YouTube-канал коледжу.
     * Використовується у блоці-заклику на сторінці /video. Значення порожнє:
     * адміністратор заповнює його сам (пункт зʼявиться у «Що заповнити»).
     */
    public function up(): void
    {
        $exists = DB::table('settings')->where('key', 'social_youtube')->exists();

        if (! $exists) {
            DB::table('settings')->insert([
                'key' => 'social_youtube',
                'value' => null,
                'group' => 'social',
                'type' => 'url',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /** Контент не видаляємо — ключ міг бути заповнений вручну. */
    public function down(): void
    {
        //
    }
};
