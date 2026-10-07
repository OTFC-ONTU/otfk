<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Log;

/**
 * Журнал безпеки адмінпанелі: кожен вхід, невдала спроба, блокування за
 * перебір і вихід пишуться в канал `security` (storage/logs/security-*.log,
 * 90 днів) — лише e-mail, IP і User-Agent, без паролів. Успішний вхід також
 * оновлює users.last_login_at/last_login_ip для колонки «Останній вхід».
 * Реєструється автоматично (event discovery в app/Listeners).
 */
class LogAuthenticationEvents
{
    public function handle(Login|Failed|Lockout|Logout $event): void
    {
        $request = request();
        $context = [
            'ip' => $request?->ip(),
            'user_agent' => mb_substr((string) $request?->userAgent(), 0, 255),
        ];

        if ($event instanceof Lockout) {
            Log::channel('security')->warning('auth.lockout', $context + ['email' => $event->request->input('email')]);

            return;
        }

        if ($event instanceof Failed) {
            Log::channel('security')->warning('auth.failed', $context + [
                'email' => $event->credentials['email'] ?? null,
                'known_user' => $event->user !== null,
            ]);

            return;
        }

        $user = $event->user;
        $context['email'] = $user->getAuthIdentifierName() === 'email' ? $user->getAuthIdentifier() : ($user->email ?? null);
        $context['user_id'] = $user->getAuthIdentifier();

        if ($event instanceof Login) {
            if ($user instanceof User) {
                $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $context['ip']])->saveQuietly();
            }

            Log::channel('security')->info('auth.login', $context + ['remember' => $event->remember]);

            return;
        }

        Log::channel('security')->info('auth.logout', $context);
    }
}
