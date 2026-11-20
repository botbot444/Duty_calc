<?php

use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [SetLocale::class]);

        // Admin JSON endpoints answer guests with 401; the admin pages send them to the sign-in page.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : '/admin/login');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // JSON endpoints live in routes/web.php under /api/ (for session + CSRF), so tell
        // Laravel to render their errors (422 validation, 404, 419 CSRF) as JSON, never a redirect.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
        );
    })->create();
