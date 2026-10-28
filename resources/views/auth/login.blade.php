@extends('layouts.guest')
@section('title', 'Connexion')

@section('content')
<div class="card">
    <h1>Connexion</h1>
    <p class="muted">Prospection &amp; Marketing — Entreprise AR</p>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ url('/connexion') }}">
        @csrf
        <div class="field">
            <label for="email">Adresse e-mail</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
        </div>
        <div class="field">
            <label for="password">Mot de passe</label>
            <input id="password" type="password" name="password" required autocomplete="current-password">
        </div>
        <div class="field">
            <label class="checkbox"><input type="checkbox" name="remember" value="1"> Rester connecté</label>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Se connecter</button>
        </div>
    </form>
</div>
@endsection
