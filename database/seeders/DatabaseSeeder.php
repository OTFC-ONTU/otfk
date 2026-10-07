<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Перший адміністратор береться з ADMIN_EMAIL / ADMIN_PASSWORD. Поза
     * local/testing пароль обов'язковий: репозиторій публічний, тому
     * стандартного пароля-заглушки на реальному сервері бути не може.
     * Повторний запуск не перезаписує пароль і роль наявного користувача.
     */
    public function run(): void
    {
        $email = (string) env('ADMIN_EMAIL', 'admin@otfk.od.ua');
        $password = (string) env('ADMIN_PASSWORD', '');

        if ($password === '') {
            if (! app()->environment(['local', 'testing'])) {
                throw new \RuntimeException('Задайте ADMIN_PASSWORD у .env перед сидуванням: стандартний пароль на сервері заборонено.');
            }

            $password = 'password';
        }

        User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Адміністратор',
                'role' => User::ROLE_ADMIN,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]
        );

        $this->call([SiteSeeder::class, QuizSeeder::class]);
    }
}
