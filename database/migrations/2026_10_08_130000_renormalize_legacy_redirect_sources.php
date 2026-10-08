<?php

use App\Support\LegacyRedirects;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Нормалізація старих адрес змінилася (/x/index.php = /x, порожній перелік
 * значущих параметрів): записи, імпортовані раніше, отримують новий шлях і хеш,
 * інакше запити з новим хешем їх не знаходять. Ідемпотентно; запис, чий новий
 * хеш уже зайнятий іншим, лишається без змін (його видно в адмінці як дубль).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('legacy_redirects')) {
            return;
        }

        foreach (DB::table('legacy_redirects')->orderBy('id')->get(['id', 'source_path', 'source_query', 'source_hash']) as $row) {
            [$path, $query] = LegacyRedirects::normalize($row->source_path, $row->source_query);
            $hash = LegacyRedirects::hash($path, $query);
            if ($hash === $row->source_hash || DB::table('legacy_redirects')->where('source_hash', $hash)->exists()) {
                continue;
            }
            DB::table('legacy_redirects')->where('id', $row->id)->update([
                'source_path' => $path,
                'source_query' => $query,
                'source_hash' => $hash,
            ]);
        }

        LegacyRedirects::flush();
    }

    public function down(): void
    {
        // Попередня нормалізація не відновлюється: нові хеші відповідають коду.
    }
};
