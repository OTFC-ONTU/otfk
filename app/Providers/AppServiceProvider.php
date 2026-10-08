<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Єдина політика паролів адмінки (UserResource, профіль Filament):
        // щонайменше 12 символів з літерами й цифрами; у production додатково
        // перевірка за базою витоків haveibeenpwned (k-анонімність, без
        // передачі пароля; за недоступності сервісу перевірка пропускається).
        Password::defaults(function () {
            $rule = Password::min(12)->letters()->numbers();

            return $this->app->isProduction() ? $rule->uncompromised() : $rule;
        });
    }
}
