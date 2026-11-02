@extends('layouts.app')
@section('title', 'Sécurité de mon compte')

@section('content')
<div class="page-header">
    <div>
        <h1>Sécurité de mon compte</h1>
        <p class="subtitle">La double authentification demande, en plus du mot de passe, un code affiché sur votre téléphone. Même si votre mot de passe est volé, personne ne peut entrer.</p>
    </div>
</div>

@if ($recoveryCodes)
    <section class="card outcome-card">
        <h2><x-icon name="shield-check" /> Vos codes de secours</h2>
        <p>À conserver en lieu sûr (gestionnaire de mots de passe, papier). Chacun permet de se connecter <strong>une fois</strong> si vous perdez votre téléphone. Ils ne seront plus affichés.</p>
        <ul class="recovery-codes">@foreach ($recoveryCodes as $c)<li><code>{{ $c }}</code></li>@endforeach</ul>
    </section>
@endif

@if ($user->hasTwoFactor())
    <section class="card">
        <h2><span class="badge badge-success">Activée</span> Double authentification</h2>
        <p class="muted small">Activée le {{ $user->two_factor_confirmed_at->format('d/m/Y') }}. {{ count((array) $user->two_factor_recovery_codes) }} code(s) de secours restant(s).</p>
        <div class="grid grid-2">
            <form method="POST" action="{{ route('pm.security.codes') }}">
                @csrf
                <h3>Nouveaux codes de secours</h3>
                <div class="field"><label for="pw1">Mot de passe</label><input type="password" id="pw1" name="password" required autocomplete="current-password"></div>
                <button type="submit" class="btn btn-sm">Générer de nouveaux codes</button>
            </form>
            @unless ($required)
                <form method="POST" action="{{ route('pm.security.disable') }}" data-confirm="Désactiver la double authentification ?">
                    @csrf
                    <h3>Désactiver</h3>
                    <div class="field"><label for="pw2">Mot de passe</label><input type="password" id="pw2" name="password" required autocomplete="current-password"></div>
                    <button type="submit" class="btn btn-sm btn-danger">Désactiver</button>
                </form>
            @else
                <p class="small muted">Obligatoire pour votre rôle : elle ne peut pas être désactivée.</p>
            @endunless
        </div>
        @error('password')<div class="error">{{ $message }}</div>@enderror
    </section>
@else
    <section class="card">
        <h2>Activer en 3 étapes</h2>
        <ol class="howto big">
            <li>Installez sur votre téléphone <strong>Google Authenticator</strong> ou <strong>Microsoft Authenticator</strong> (gratuits).</li>
            <li>Dans l'application, touchez « + » puis « Scanner un code QR » et scannez ce code :
                <div class="qr-box"><img data-qr="{{ $uri }}" alt="Code QR à scanner" width="200" height="200"></div>
                <span class="small muted block">Impossible de scanner ? Saisissez cette clé : <code class="break">{{ trim(chunk_split($secret, 4, ' ')) }}</code></span>
            </li>
            <li>Saisissez le code à 6 chiffres affiché par l'application :
                <form method="POST" action="{{ route('pm.security.enable') }}" class="toolbar section-gap">
                    @csrf
                    <label class="sr-only" for="code">Code</label>
                    <input type="text" id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="10" required placeholder="123 456" class="code-input">
                    <button type="submit" class="btn btn-primary">Activer</button>
                </form>
                @error('code')<div class="error">{{ $message }}</div>@enderror
            </li>
        </ol>
    </section>
@endif
@endsection

@push('head')
<script src="{{ asset('vendor/qrcode/qrcode.js') }}" defer></script>
@endpush
