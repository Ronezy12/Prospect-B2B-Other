@extends('layouts.app')
@section('title', 'Réseaux sociaux')

@section('content')
<div class="page-header">
    <div>
        <h1>Réseaux sociaux</h1>
        <p class="subtitle">Suivi des prises de contact réalisées par l'équipe sur LinkedIn, Facebook, Instagram… · {{ $counts['a_traiter'] }} à traiter aujourd'hui</p>
    </div>
    <div class="actions">
        @can('social.inbox')<a class="btn" href="{{ route('pm.social.inbox.index') }}"><x-icon name="messages-square" /> Messages de la Page</a>@endcan
        @can('social.templates')<a class="btn" href="{{ route('pm.social.templates.index') }}"><x-icon name="copy" /> Modèles de messages</a>@endcan
    </div>
</div>

<div class="alert alert-info"><x-icon name="shield-check" /><span>Les invitations et messages sont envoyés <strong>par vous, depuis la plateforme</strong>, puis consignés ici. Aucune action n'est automatisée sur vos comptes personnels et aucun profil n'est collecté automatiquement, conformément aux conditions d'utilisation des réseaux.</span></div>

<nav class="tabs" aria-label="Vues">
    @foreach ($views as $key => $label)
        <a href="{{ route('pm.social.index', ['vue' => $key] + array_filter($filters)) }}" @class(['tab', 'active' => $view === $key])>{{ $label }} @isset($counts[$key])<span class="count">{{ $counts[$key] }}</span>@endisset</a>
    @endforeach
</nav>

<form method="GET" class="toolbar">
    <input type="hidden" name="vue" value="{{ $view }}">
    <label class="sr-only" for="platform">Réseau</label>
    <select id="platform" name="platform"><option value="">Tous les réseaux</option>@foreach (config('prospection.social.platforms') as $key => $p)<option value="{{ $key }}" @selected(($filters['platform'] ?? '') === $key)>{{ $p['label'] }}</option>@endforeach</select>
    <label class="sr-only" for="status">Statut</label>
    <select id="status" name="status"><option value="">Tous les statuts</option>@foreach (config('prospection.social.statuses') as $key => $label)<option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>@endforeach</select>
    <label class="sr-only" for="owner_id">Responsable</label>
    <select id="owner_id" name="owner_id"><option value="">Tous les responsables</option>@foreach ($users as $user)<option value="{{ $user->id }}" @selected((string) ($filters['owner_id'] ?? '') === (string) $user->id)>{{ $user->name }}</option>@endforeach</select>
    <button type="submit" class="btn"><x-icon name="filter" /> Filtrer</button>
</form>

<div class="card flush">
    @forelse ($profiles as $profile)
        <div class="task-row">
            <div class="grow">
                <a class="t-title" href="{{ route('pm.social.show', $profile) }}">{{ $profile->company->name }}@if ($profile->contact) — {{ $profile->contact->fullName() }}@endif</a>
                <div class="t-meta">
                    @include('social.partials.platform', ['platform' => $profile->platform])
                    @include('social.partials.status', ['status' => $profile->status])
                    @if ($profile->next_action_at)<span @class(['late' => $profile->next_action_at->lt(today()) && ! $profile->isClosed()])><x-icon name="alarm-clock" /> {{ $profile->next_action_at->format('d/m/Y') }}</span>@endif
                    @if ($profile->last_interaction_at)<span>Dernière action {{ $profile->last_interaction_at->diffForHumans() }}</span>@endif
                    <span>{{ $profile->company->owner?->name ?? 'Non assigné' }}</span>
                </div>
            </div>
            @unless ($profile->isOpposed())
                <a class="btn btn-sm" href="{{ $profile->url }}" target="_blank" rel="noopener noreferrer nofollow"><x-icon name="external-link" /> Ouvrir le profil</a>
            @endunless
            <a class="btn btn-sm btn-primary" href="{{ route('pm.social.show', $profile) }}">Traiter</a>
        </div>
    @empty
        <div class="empty-state"><x-icon name="share-2" /><strong>Aucun profil dans cette vue</strong>Ajoutez l'adresse d'un profil ou d'une page depuis la fiche d'un prospect (rubrique « Réseaux sociaux »), ou importez une colonne LinkedIn.</div>
    @endforelse
    {{ $profiles->links('partials.pagination') }}
</div>
@endsection
