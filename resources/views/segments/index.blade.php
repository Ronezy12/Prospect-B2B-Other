@extends('layouts.app')
@section('title', 'Listes de destinataires')

@section('content')
<div class="page-header">
    <div>
        <h1>Listes de destinataires</h1>
        <p class="subtitle">Les entreprises qui recevront un e-mail groupé, choisies par critères (ex. agences immobilières de Lyon). La liste se met à jour toute seule au moment de l'envoi.</p>
    </div>
    @can('campaigns.manage')<div class="actions"><a class="btn btn-primary" href="{{ route('pm.segments.create') }}"><x-icon name="plus" /> Nouvelle liste</a></div>@endcan
</div>
<div class="card flush">
    @forelse ($segments as $segment)
        <div class="settings-row">
            <div class="grow">
                <a class="row-title" href="{{ route('pm.segments.edit', $segment) }}">{{ $segment->name }}</a>
                <div class="sub">{{ count($segment->filters) }} critère(s) · modifié le {{ $segment->updated_at->format('d/m/Y') }}</div>
            </div>
        </div>
    @empty
        <div class="empty-state"><x-icon name="filter" /><strong>Aucune liste</strong>Créez une liste pour choisir à qui envoyer vos e-mails groupés : secteur, ville, statut, tags…</div>
    @endforelse
    {{ $segments->links('partials.pagination') }}
</div>
@endsection
