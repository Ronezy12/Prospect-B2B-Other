<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Les rôles sensibles (administrateur par défaut) doivent activer la double authentification. */
class EnsureTwoFactorEnrolled
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && $user->mustUseTwoFactor() && ! $user->hasTwoFactor() && ! $request->routeIs('pm.security.*', 'logout')) {
            return redirect()->route('pm.security.show')->with('warning', 'Votre rôle exige la double authentification : activez-la pour continuer (2 minutes).');
        }

        return $next($request);
    }
}
