<?php

namespace App\Http\Middleware;

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

        // Англійський каркас ще не готовий до індексації.
        if ($english) {
            $response->headers->set('X-Robots-Tag', 'noindex, follow');
        }

        return $response;
    }
}
