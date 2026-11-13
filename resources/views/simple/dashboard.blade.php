@extends('layouts.app')
@section('title', 'Ma journée')

@section('content')
<section class="s-hello">
    <h1>Bonjour {{ \Illuminate\Support\Str::before(auth()->user()->name, ' ') }} 👋</h1>
    <p>
        @if ($toCallToday->isNotEmpty())
            Aujourd'hui, <strong>{{ $toCallToday->count() }} {{ $toCallToday->count() > 1 ? 'personnes' : 'personne' }}</strong> à appeler. Appelez, puis touchez le bon bouton : c'est tout.
        @else
            Rien à faire pour l'instant. Ajoutez un prospect pour commencer.
        @endif
    </p>
</section>

<div class="s-big">
    @can('prospects.create')
        <a class="s-big-btn yellow" href="{{ route('pm.companies.create') }}">➕<strong>Ajouter un prospect</strong></a>
    @endcan
    <a class="s-big-btn" href="{{ route('pm.companies.index') }}">📋<strong>Voir mes prospects</strong></a>
    @can('campaigns.manage')
        <a class="s-big-btn" href="{{ route('pm.simple-email.create') }}">✉️<strong>Écrire à plusieurs entreprises</strong></a>
    @endcan
</div>

<h2 class="s-title">📞 À appeler aujourd'hui</h2>
@forelse ($toCallToday as $item)
    @include('simple.call-card', ['company' => $item['company'], 'why' => $item['why']])
@empty
    <div class="s-empty">🎉 Personne à appeler pour le moment.</div>
@endforelse
@endsection
