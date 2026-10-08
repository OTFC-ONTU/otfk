<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Перенесення старого сайту (docs/seo-plan.md, етап 1): карта старих адрес
 * (301/308 або 410) і журнал 404. Обидві таблиці читаються лише тоді, коли
 * застосунок уже формує відповідь 404, тож живі сторінки вони не перекривають.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('legacy_redirects')) {
            Schema::create('legacy_redirects', function (Blueprint $table) {
                $table->id();
                // sha256 нормалізованих path + значущих параметрів: унікальний ключ без обмеження довжини індексу MySQL.
                $table->char('source_hash', 64)->unique();
                $table->text('source_path');
                $table->string('source_query', 500)->nullable();
                $table->string('action', 16)->default('redirect'); // redirect | gone
                $table->text('target_url')->nullable();
                // Хеші нормалізованого призначення (повний і лише шлях) — швидка перевірка ланцюжків.
                $table->char('target_hash', 64)->nullable()->index();
                $table->char('target_path_hash', 64)->nullable()->index();
                $table->unsignedSmallInteger('status_code')->default(301); // 301 | 308 | 410
                $table->boolean('is_active')->default(true);
                $table->string('note', 500)->nullable();
                $table->unsignedInteger('hits')->default(0);
                $table->timestamp('last_hit_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('not_found_logs')) {
            Schema::create('not_found_logs', function (Blueprint $table) {
                $table->id();
                $table->char('path_hash', 64)->unique();
                $table->text('path');
                $table->string('query', 500)->nullable();
                $table->string('referrer', 500)->nullable();
                $table->unsignedInteger('hits')->default(1);
                $table->timestamp('first_seen_at')->nullable();
                $table->timestamp('last_seen_at')->nullable()->index();
                $table->string('status', 16)->default('new'); // new | resolved | ignored
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('not_found_logs');
        Schema::dropIfExists('legacy_redirects');
    }
};
