<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\SecurityHeaders;
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
    ->withMiddleware(function (Middleware $middleware): void {
        // Derrière un hébergeur (Render, répartiteur de charge…) : HTTPS et IP réelles via X-Forwarded-*.
        // Ne définir TRUSTED_PROXIES que si un proxy est réellement devant l'application.
        if ($proxies = env('TRUSTED_PROXIES')) {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }
        $middleware->web(append: [
            EnsureUserIsActive::class,
            SecurityHeaders::class,
        ]);
        // Le « clic unique » des messageries (RFC 8058) ne peut pas porter de jeton CSRF ;
        // la route est protégée par une URL signée.
        $middleware->validateCsrfTokens(except: ['desinscription/*', 'webhooks/*']);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('pm.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
