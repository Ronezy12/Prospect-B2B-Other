<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        // HTTPS imposé au navigateur pendant un an (uniquement quand le site est servi en HTTPS).
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
        // Les pages connectées contiennent des données personnelles : pas de mise en cache.
        if ($request->user() && str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }
        if (! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', "default-src 'self'; img-src 'self' data: https:; style-src 'self'; script-src 'self'; frame-ancestors 'self'; form-action 'self'; base-uri 'self'; object-src 'none'");
        }

        return $response;
    }
}
