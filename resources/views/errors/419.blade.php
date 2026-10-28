@extends('layouts.guest')
@section('title', 'Session expirée')

@section('content')
<div class="card">
    <h1>Session expirée</h1>
    <p>Votre session a expiré. Rechargez la page et recommencez.</p>
    <a class="btn" href="{{ url('/') }}">Retour à l'accueil</a>
</div>
@endsection
