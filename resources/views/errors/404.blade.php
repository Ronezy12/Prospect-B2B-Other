@extends('layouts.guest')
@section('title', 'Page introuvable')

@section('content')
<div class="card">
    <h1>Page introuvable</h1>
    <p>La page demandée n'existe pas ou n'est plus disponible.</p>
    <a class="btn" href="{{ url('/') }}">Retour à l'accueil</a>
</div>
@endsection
