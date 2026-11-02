<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use App\Support\Totp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Page « Sécurité » de chaque utilisateur : activation de la double authentification. */
class SecurityController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function show(Request $request): View
    {
        $user = $request->user();
        $secret = null;
        if (! $user->hasTwoFactor()) {
            // Le secret n'est enregistré qu'après confirmation d'un premier code.
            $secret = $request->session()->get('two_factor.setup') ?? tap(Totp::generateSecret(), fn ($s) => $request->session()->put('two_factor.setup', $s));
        }

        return view('security.show', [
            'user' => $user,
            'secret' => $secret,
            'uri' => $secret ? Totp::uri($secret, $user->email, (string) config('prospection.security.two_factor_issuer')) : null,
            'recoveryCodes' => $request->session()->pull('two_factor.recovery_codes'),
            'required' => $user->mustUseTwoFactor(),
        ]);
    }

    public function enable(Request $request): RedirectResponse
    {
        $user = $request->user();
        $secret = $request->session()->get('two_factor.setup');
        $code = $request->validate(['code' => ['required', 'string', 'max:10']])['code'];
        if (! $secret || $user->hasTwoFactor() || Totp::verify($secret, $code) === null) {
            return back()->withErrors(['code' => 'Code incorrect : vérifiez l\'heure de votre téléphone et réessayez avec le code affiché.']);
        }
        $codes = $this->newRecoveryCodes();
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => array_map(fn ($c) => Hash::make(str_replace('-', '', $c)), $codes),
            'two_factor_confirmed_at' => now(),
        ])->save();
        $request->session()->forget('two_factor.setup');
        $request->session()->flash('two_factor.recovery_codes', $codes);
        $this->audit->log('security.two_factor_enabled', $user);

        return redirect()->route('pm.security.show')->with('success', 'Double authentification activée. Notez vos codes de secours ci-dessous.');
    }

    public function regenerate(Request $request): RedirectResponse
    {
        $user = $request->user();
        $request->validate(['password' => ['required', 'current_password']], ['password.current_password' => 'Mot de passe incorrect.']);
        abort_unless($user->hasTwoFactor(), 404);
        $codes = $this->newRecoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => array_map(fn ($c) => Hash::make(str_replace('-', '', $c)), $codes)])->save();
        $request->session()->flash('two_factor.recovery_codes', $codes);
        $this->audit->log('security.recovery_codes_regenerated', $user);

        return back()->with('success', 'Nouveaux codes de secours générés : les anciens ne fonctionnent plus.');
    }

    public function disable(Request $request): RedirectResponse
    {
        $user = $request->user();
        $request->validate(['password' => ['required', 'current_password']], ['password.current_password' => 'Mot de passe incorrect.']);
        if ($user->mustUseTwoFactor()) {
            return back()->withErrors(['password' => 'La double authentification est obligatoire pour votre rôle.']);
        }
        $user->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();
        $this->audit->log('security.two_factor_disabled', $user);

        return back()->with('success', 'Double authentification désactivée.');
    }

    /** @return array<int, string> */
    private function newRecoveryCodes(): array
    {
        return collect(range(1, 8))->map(fn () => strtoupper(Str::random(5).'-'.Str::random(5)))->all();
    }
}
