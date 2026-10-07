<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ролі адмінпанелі та журнал входів.
 *
 * `role` — admin (повний доступ, керує обліковими записами й налаштуваннями)
 * або editor (лише контент). Усі наявні користувачі стають адміністраторами,
 * бо до цієї міграції кожен обліковий запис мав повний доступ; нові записи
 * за замовчуванням — редактори. `last_login_at`/`last_login_ip` заповнює
 * слухач подій автентифікації (App\Listeners\LogAuthenticationEvents).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'role')) {
                $table->string('role', 20)->default('editor')->after('email');
            }
            if (! Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->after('remember_token');
            }
            if (! Schema::hasColumn('users', 'last_login_ip')) {
                $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
            }
        });

        DB::table('users')->update(['role' => 'admin']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(array_values(array_filter(
                ['role', 'last_login_at', 'last_login_ip'],
                fn (string $column) => Schema::hasColumn('users', $column),
            )));
        });
    }
};
