@extends('layouts.guest')
@section('title', 'Trop de requêtes')

@section('content')
<div class="card">
    <h1>Trop de requêtes</h1>
    <p>Merci de patienter quelques instants avant de réessayer.</p>
    <a class="btn" href="{{ url('/') }}">Retour à l'accueil</a>
</div>
@endsection
