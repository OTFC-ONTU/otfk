<?php

namespace App\Http\Middleware;

use App\Support\Seo;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetPublicLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        // Мова визначається лише адресою, адмінка завжди українська.
        $english = $request->segment(1) === 'en';
        app()->setLocale($english ? 'en' : 'uk');

        $response = $next($request);

        // Тестовий хостинг і локальні копії закриті від індексації повністю;
        // robots.txt обхід не забороняє, щоб робот побачив цю директиву.
        if (! Seo::indexable($request)) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        } elseif ($english && (! $response->isSuccessful() || ! Seo::englishIndexable($request))) {
            // /en індексується посторінково (Seo::englishIndexable): розділи з
            // перекладеним каркасом і матеріали з повним незастарілим перекладом.
            // Український fallback, службові відповіді та помилки — noindex.
            $response->headers->set('X-Robots-Tag', 'noindex, follow');
        }

        return $response;
    }
}
