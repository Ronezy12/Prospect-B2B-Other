<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Totp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Deuxième étape de connexion : code de l'application d'authentification ou code de secours. */
class TwoFactorChallengeController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        return $this->pending($request) ? view('auth.two-factor') : redirect()->route('login');
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $user = $this->pending($request);
        if (! $user) {
            return redirect()->route('login')->withErrors(['email' => 'Session expirée : reconnectez-vous.']);
        }
        $data = $request->validate(['code' => ['required', 'string', 'max:32']], [], ['code' => 'code']);

        $key = 'two-factor|'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['code' => 'Trop de tentatives. Réessayez dans '.RateLimiter::availableIn($key).' secondes.']);
        }

        $code = trim($data['code']);
        $step = Totp::verify((string) $user->two_factor_secret, $code);
        // Un même code ne peut pas servir deux fois (rejeu).
        $valid = $step !== null && Cache::add('two-factor-used|'.$user->id.'|'.$step, true, now()->addMinutes(3));
        $usedRecovery = false;
        if (! $valid && strlen(preg_replace('/\W/', '', $code)) > 6) {
            $valid = $usedRecovery = $this->useRecoveryCode($user, $code);
        }
        if (! $valid) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['code' => 'Code incorrect.']);
        }

        RateLimiter::clear($key);
        $remember = (bool) $request->session()->get('login.two_factor.remember');
        $request->session()->forget('login.two_factor');
        Auth::login($user, $remember);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();
        $audit->log('auth.login', $user, ['double_authentification' => $usedRecovery ? 'code de secours' : 'application']);

        return redirect()->intended(route('pm.dashboard'))->with($usedRecovery ? 'warning' : 'success', $usedRecovery
            ? 'Connecté avec un code de secours. Il ne resservira pas : pensez à en régénérer dans « Sécurité ».'
            : 'Connexion réussie.');
    }

    private function pending(Request $request): ?User
    {
        $pending = $request->session()->get('login.two_factor');
        if (! $pending || ($pending['expires'] ?? 0) < now()->timestamp) {
            return null;
        }

        return User::where('is_active', true)->find($pending['id']);
    }

    private function useRecoveryCode(User $user, string $code): bool
    {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));
        $codes = (array) $user->two_factor_recovery_codes;
        foreach ($codes as $i => $hash) {
            if (Hash::check($code, $hash)) {
                unset($codes[$i]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

                return true;
            }
        }

        return false;
    }
}
