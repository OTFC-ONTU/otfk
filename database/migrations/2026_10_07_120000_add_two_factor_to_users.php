<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Двофакторна автентифікація (TOTP, Google Authenticator) для всіх користувачів
 * адмінки: секрет і коди відновлення зберігаються зашифрованими (cast
 * `encrypted`), `two_factor_confirmed_at` — момент підтвердження першим кодом,
 * `two_factor_last_used` — часовий крок останнього прийнятого коду (захист
 * від повторного використання того самого коду).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'two_factor_secret')) {
                $table->text('two_factor_secret')->nullable()->after('remember_token');
            }
            if (! Schema::hasColumn('users', 'two_factor_recovery_codes')) {
                $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            }
            if (! Schema::hasColumn('users', 'two_factor_confirmed_at')) {
                $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
            }
            if (! Schema::hasColumn('users', 'two_factor_last_used')) {
                $table->unsignedBigInteger('two_factor_last_used')->nullable()->after('two_factor_confirmed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(array_values(array_filter(
                ['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at', 'two_factor_last_used'],
                fn (string $column) => Schema::hasColumn('users', $column),
            )));
        });
    }
};
