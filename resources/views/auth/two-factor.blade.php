@extends('layouts.guest')
@section('title', 'Vérification')
@section('bare', true)

@section('content')
<div class="auth auth-single">
    <main class="auth-main">
        <div class="auth-card">
            <img class="auth-logo" src="{{ asset('img/logo-entreprise-ar.png') }}" alt="Entreprise AR" width="160" height="157">
            <h1>Vérification</h1>
            <p class="muted">Ouvrez votre application d'authentification (Google Authenticator, Microsoft Authenticator…) et saisissez le code à 6 chiffres.</p>
            @if ($errors->any())<div class="alert alert-danger" role="alert"><x-icon name="circle-alert" /> <span>{{ $errors->first() }}</span></div>@endif
            <form method="POST" action="{{ route('two-factor.challenge') }}">
                @csrf
                <div class="field">
                    <label for="code">Code</label>
                    <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" required autofocus maxlength="32" placeholder="123 456" class="code-input">
                    <div class="hint">Téléphone perdu ? Saisissez l'un de vos codes de secours (format XXXXX-XXXXX).</div>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Valider</button>
            </form>
            <p class="small"><a href="{{ route('login') }}">Revenir à la connexion</a></p>
        </div>
    </main>
</div>
@endsection
