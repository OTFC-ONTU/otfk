<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Базові захисні заголовки для всіх відповідей: публічна частина (група
     * `web`) і адмінпанель (стек middleware панелі в AdminPanelProvider —
     * маршрути Filament не входять до групи `web`). Повний CSP свідомо не
     * додаємо: Livewire/Alpine/Filament покладаються на інлайн-скрипти, і
     * строгий CSP їх зламає; обмежуємося frame-ancestors проти clickjacking.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Content-Security-Policy', "frame-ancestors 'self'");
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // Адмінку й її службові адреси не індексуємо незалежно від robots.txt.
        if ($request->is('admin', 'admin/*', 'admin-preview/*', 'livewire/*')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        // HSTS — лише по HTTPS, 180 днів (без preload: домен тестовий, лишаємо шлях назад)
        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=15552000');
        }

        return $response;
    }
}
