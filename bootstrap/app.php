<?php

use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetPublicLocale;
use App\Http\Middleware\TrackVisits;
use App\Support\LegacyRedirects;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(SetPublicLocale::class);
        $middleware->web(append: [
            SecurityHeaders::class,
            TrackVisits::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Стара адреса: редирект/410 з карти legacy_redirects, інакше — запис у журнал 404.
        // Викликається лише для вже сформованого 404, тож живі сторінки не перекриваються.
        $exceptions->render(fn (NotFoundHttpException $e, Request $request) => LegacyRedirects::handleNotFound($request));
    })->create();
