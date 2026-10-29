@extends('layouts.guest')
@section('title', 'Désinscription')

@section('content')
<div class="card">
    <h1>{{ $done ? 'Désinscription enregistrée' : 'Se désinscrire' }}</h1>
    @if ($done)
        <p>L'adresse <strong>{{ $recipient->email }}</strong> ne recevra plus {{ $scope }}. Cette demande est prise en compte immédiatement et conservée durablement.</p>
    @else
        <p>Vous êtes sur le point de ne plus recevoir {{ $scope }} à l'adresse <strong>{{ $recipient->email }}</strong>.</p>
        <form method="POST" action="{{ request()->fullUrl() }}">
            <button type="submit" class="btn btn-primary">Confirmer la désinscription</button>
        </form>
    @endif
    <p class="small muted section-gap">Entreprise AR — pour toute demande relative à vos données personnelles, répondez à l'e-mail reçu.</p>
</div>
@endsection
