<?php

namespace App\Filament\Auth;

use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Notifications\Notification;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Support\Facades\Log;

/**
 * Сторінка входу Filament з журналом блокувань: ліміт 5 спроб/хв на IP
 * реалізовано пакетом livewire-rate-limiting, який не надсилає подію
 * Illuminate\Auth\Events\Lockout, тому запис auth.lockout робиться тут
 * (той самий канал `security`, що й у LogAuthenticationEvents).
 */
class Login extends BaseLogin
{
    protected function getRateLimitedNotification(TooManyRequestsException $exception): ?Notification
    {
        Log::channel('security')->warning('auth.lockout', [
            'ip' => request()->ip(),
            'user_agent' => mb_substr((string) request()->userAgent(), 0, 255),
            'email' => $this->data['email'] ?? null,
            'seconds' => $exception->secondsUntilAvailable,
        ]);

        return parent::getRateLimitedNotification($exception);
    }
}
