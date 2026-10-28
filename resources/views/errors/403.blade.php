@extends('layouts.guest')
@section('title', 'Accès refusé')

@section('content')
<div class="card">
    <h1>Accès refusé</h1>
    <p>Vous n'avez pas les droits nécessaires pour cette action.</p>
    <a class="btn" href="{{ url('/') }}">Retour à l'accueil</a>
</div>
@endsection
