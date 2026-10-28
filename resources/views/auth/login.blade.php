@extends('layouts.guest')
@section('title', 'Connexion')
@section('bare', true)

@section('content')
<div class="auth">
    <aside class="auth-aside">
        <div class="auth-brand"><span class="brand-mark">AR</span> Entreprise AR</div>
        <div>
            <h2>Toute votre prospection B2B au même endroit.</h2>
            <p>Prospects, interlocuteurs, imports et suivi commercial, avec la conformité RGPD intégrée dès la saisie.</p>
            <ul class="auth-points">
                <li><x-icon name="building-2" /> Base de prospects par secteur</li>
                <li><x-icon name="file-spreadsheet" /> Import CSV et Excel avec détection des doublons</li>
                <li><x-icon name="shield-check" /> Liste d'opposition centralisée</li>
            </ul>
        </div>
        <span class="small">Accès réservé aux équipes Entreprise AR</span>
    </aside>
    <main class="auth-main">
        <div class="auth-card">
            <h1>Bon retour</h1>
            <p class="muted">Connectez-vous pour accéder à votre espace.</p>

            @if ($errors->any())
                <div class="alert alert-danger" role="alert"><x-icon name="circle-alert" /> <span>{{ $errors->first() }}</span></div>
            @endif

            <form method="POST" action="{{ url('/connexion') }}">
                @csrf
                <div class="field">
                    <label for="email">Adresse e-mail</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="prenom.nom@entreprise-ar.fr">
                </div>
                <div class="field">
                    <label for="password">Mot de passe</label>
                    <input id="password" type="password" name="password" required autocomplete="current-password">
                </div>
                <div class="field">
                    <label class="checkbox"><input type="checkbox" name="remember" value="1"> Rester connecté</label>
                </div>
                <button type="submit" class="btn btn-primary">Se connecter</button>
            </form>
        </div>
    </main>
</div>
@endsection
