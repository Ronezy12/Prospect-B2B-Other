@extends('layouts.guest')
@section('title', 'Erreur interne')

@section('content')
<div class="card">
    <h1>Erreur interne</h1>
    <p>Une erreur est survenue. Elle a été enregistrée ; réessayez plus tard.</p>
    <a class="btn" href="{{ url('/') }}">Retour à l'accueil</a>
</div>
@endsection
