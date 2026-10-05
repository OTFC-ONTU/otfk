<?php

use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetPublicLocale;
use App\Http\Middleware\TrackVisits;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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
        //
    })->create();
