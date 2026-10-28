@extends('layouts.guest')
@section('title', 'Maintenance')

@section('content')
<div class="card">
    <h1>Maintenance</h1>
    <p>Le service est temporairement indisponible.</p>
    <a class="btn" href="{{ url('/') }}">Retour à l'accueil</a>
</div>
@endsection
