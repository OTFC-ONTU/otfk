<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Стрічки «новіше вгорі» (HasSortOrder::sortNewRecordsFirst) дають новому запису min(sort_order) - 1.
 * Після нумерації 1…n (2026_10_09_120000) другий новий запис отримував -1, а стовпець був UNSIGNED —
 * MySQL у strict-режимі відповідав помилкою (500 при створенні документа). Робимо стовпець знаковим;
 * наявні значення не змінюються.
 */
return new class extends Migration
{
    private const TABLES = ['documents', 'galleries', 'videos', 'banners'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'sort_order')) {
                Schema::table($table, function (Blueprint $blueprint): void {
                    $blueprint->integer('sort_order')->default(0)->change();
                });
            }
        }
    }

    public function down(): void
    {
        // Повернення до UNSIGNED зламало б записи з від'ємним порядком — залишаємо знаковий стовпець.
    }
};
