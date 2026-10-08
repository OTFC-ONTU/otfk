<?php

namespace App\Http\Middleware;

use App\Models\SiteVisit;
use App\Support\Seo;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackVisits
{
    /** Шляхи, які не рахуємо: точний збіг або «префікс/…» (службове/адмінка/статика). */
    private array $skipPrefixes = [
        'admin', 'livewire', 'storage', 'build', 'vendor', 'up',
        'favicon.ico', 'robots.txt', 'sitemap.xml', 'poshuk/pidkazky',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            if ($this->shouldTrack($request, $response)) {
                $path = '/'.trim($request->path(), '/');
                $path = mb_substr($path === '/.' ? '/' : $path, 0, 180);

                SiteVisit::hit($path === '' ? '/' : $path);

                // Візит (сесія) — один раз на день на відвідувача
                $dayKey = 'visited_'.now()->toDateString();
                if (! $request->session()->has($dayKey)) {
                    $request->session()->put($dayKey, true);
                    SiteVisit::hit(SiteVisit::VISITS_PATH);
                }
            }
        } catch (\Throwable $e) {
            report($e); // статистика ніколи не має ламати сторінку
        }

        return $response;
    }

    private function shouldTrack(Request $request, Response $response): bool
    {
        if (! $request->isMethod('GET') || $response->getStatusCode() !== 200) {
            return false;
        }

        // Рахуємо лише справжніх відвідувачів основного домену: персонал, що
        // увійшов до адмінки, і тестовий хостинг/локальні копії не враховуються.
        if (auth()->check() || ! Seo::onPrimaryHost($request)) {
            return false;
        }

        $path = trim($request->path(), '/');
        $path = str_starts_with($path, 'en/') ? substr($path, 3) : $path;

        foreach ($this->skipPrefixes as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return false;
            }
        }

        // Боти не рахуються
        $ua = (string) $request->userAgent();

        return $ua !== '' && ! preg_match('/bot|crawl|spider|slurp|preview|telegram|facebook|whatsapp|curl|wget/i', $ua);
    }
}
