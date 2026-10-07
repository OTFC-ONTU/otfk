<?php

namespace App\Http\Middleware;

use App\Filament\Auth\TwoFactorChallenge;
use App\Filament\Auth\TwoFactorSetup;
use App\Models\User;
use App\Support\TwoFactor;
use Closure;
use Illuminate\Http\Request;
use Livewire\Mechanisms\ComponentRegistry;
use Symfony\Component\HttpFoundation\Response;

/**
 * Другий фактор для адмінки (стек authMiddleware панелі + persistent
 * middleware Livewire, тож перевірка діє і для Livewire-викликів компонентів):
 * користувач без підключеного застосунку потрапляє на сторінку підключення,
 * з підключеним — на сторінку коду, доки сесія не позначена як пройдена.
 * Дозволені без фактора лише самі сторінки підключення/коду і вихід.
 * TWO_FACTOR_ENFORCE=false — аварійний вимикач (config/otfk.php).
 */
class RequireTwoFactor
{
    /** Компоненти Livewire, доступні до проходження фактора. */
    private const ALLOWED_COMPONENTS = [TwoFactorChallenge::class, TwoFactorSetup::class];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! TwoFactor::enforced()) {
            return $next($request);
        }

        $twoFactor = app(TwoFactor::class);
        if ($twoFactor->passed($user)) {
            return $next($request);
        }

        if ($this->isAllowed($request)) {
            return $next($request);
        }

        $target = $user->hasTwoFactor() ? TwoFactorChallenge::getUrl() : TwoFactorSetup::getUrl();

        if ($request->expectsJson() || $request->routeIs('livewire.update')) {
            abort(403, 'Потрібне підтвердження другим фактором.');
        }

        return redirect()->guest($target);
    }

    private function isAllowed(Request $request): bool
    {
        if ($request->routeIs('filament.admin.auth.logout', 'filament.admin.pages.two-factor-challenge', 'filament.admin.pages.two-factor-setup')) {
            return true;
        }

        if (! $request->routeIs('livewire.update')) {
            return false;
        }

        // Livewire-виклик: пропускаємо лише компоненти самих сторінок 2FA.
        $components = $request->input('components', []);
        if (! is_array($components) || $components === []) {
            return false;
        }

        $registry = app(ComponentRegistry::class);
        $allowedNames = array_map(fn (string $class) => $registry->getName($class), self::ALLOWED_COMPONENTS);
        foreach ($components as $component) {
            $snapshot = json_decode((string) ($component['snapshot'] ?? ''), true);
            $name = $snapshot['memo']['name'] ?? null;
            if ($name === null || ! in_array($name, $allowedNames, true)) {
                return false;
            }
        }

        return true;
    }
}
