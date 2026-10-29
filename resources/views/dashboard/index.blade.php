@extends('layouts.app')
@section('title', 'Accueil')

@section('content')
<section class="hero">
    <div>
        <div class="eyebrow">{{ now()->locale('fr')->isoFormat('dddd D MMMM YYYY') }}</div>
        <h1>Bonjour {{ \Illuminate\Support\Str::before(auth()->user()->name, ' ') }}</h1>
        <p>
            @if ($myTasks->isNotEmpty())
                Vous avez {{ $myTasks->count() }} tâche(s) à traiter aujourd'hui{{ $socialToDo ? ' et '.$socialToDo.' profil(s) à contacter sur les réseaux' : '' }}.
            @elseif ($totals['prospects'] === 0)
                Votre base est vide : commencez par ajouter ou importer vos premiers prospects.
            @else
                Rien d'urgent aujourd'hui. {{ number_format($totals['prospects'], 0, ',', ' ') }} prospects dans votre périmètre.
            @endif
        </p>
    </div>
    <div class="actions">
        @can('prospects.import')
            <a class="btn btn-glass" href="{{ route('pm.imports.create') }}"><x-icon name="upload" /> Importer</a>
        @endcan
        @can('prospects.create')
            <a class="btn btn-white" href="{{ route('pm.companies.create') }}"><x-icon name="plus" /> Nouveau prospect</a>
        @endcan
    </div>
</section>

<div class="welcome-banner">
    <x-icon name="circle-help" />
    <div class="grow"><strong>Nouveau sur la plateforme ?</strong> <span class="muted">Le guide explique les mots utilisés et chaque étape, en 5 minutes.</span></div>
    <a class="btn btn-sm btn-primary" href="{{ route('pm.guide') }}">Ouvrir le guide</a>
</div>

<h2 class="section-title">Comment fonctionne le module</h2>
<ol class="journey">
    <li class="journey-step">
        <span class="journey-num">1</span>
        <strong>Constituer la base</strong>
        <span class="muted small">Ajoutez vos prospects un par un ou importez un fichier Excel / CSV.</span>
        <span class="step-figure">{{ number_format($totals['prospects'], 0, ',', ' ') }} <small>prospects</small></span>
        <a class="btn btn-sm" href="{{ route('pm.companies.index') }}">Voir les prospects <x-icon name="arrow-right" /></a>
    </li>
    <li class="journey-step">
        <span class="journey-num">2</span>
        <strong>Suivre les affaires</strong>
        <span class="muted small">Chaque affaire avance de colonne en colonne, avec des rappels de relance.</span>
        <span class="step-figure">{{ $openOpportunities }} <small>en cours</small></span>
        <a class="btn btn-sm" href="{{ route('pm.pipelines.show') }}">Ouvrir le pipeline <x-icon name="arrow-right" /></a>
    </li>
    <li class="journey-step">
        <span class="journey-num">3</span>
        <strong>Contacter</strong>
        <span class="muted small">Par e-mail (à une personne ou à plusieurs entreprises d’un coup) ou sur les réseaux sociaux.</span>
        @canany(['campaigns.view', 'social.use'])
            <span class="step-figure">@can('campaigns.view'){{ $campaignsThisMonth }} <small>e-mail(s) groupé(s) ce mois</small>@else{{ $socialToDo }} <small>profil(s) à contacter</small>@endcan</span>
            <span class="step-links">
                @can('campaigns.view')<a class="btn btn-sm" href="{{ route('pm.campaigns.index') }}">E-mails <x-icon name="arrow-right" /></a>@endcan
                @can('social.use')<a class="btn btn-sm" href="{{ route('pm.social.index') }}">Réseaux <x-icon name="arrow-right" /></a>@endcan
            </span>
        @else
            <span class="muted small">Utilisez le bouton « E-mail » d'une fiche contact.</span>
        @endcanany
    </li>
    <li class="journey-step">
        <span class="journey-num">4</span>
        <strong>Mesurer et automatiser</strong>
        <span class="muted small">Suivez les résultats et laissez les scénarios créer les relances à votre place.</span>
        <span class="step-figure">{{ number_format($totals['clients'], 0, ',', ' ') }} <small>client(s) gagné(s)</small></span>
        <span class="step-links">
            @can('reports.view')<a class="btn btn-sm" href="{{ route('pm.reports.index') }}">Statistiques <x-icon name="arrow-right" /></a>@endcan
            @can('automations.manage')<a class="btn btn-sm" href="{{ route('pm.automations.index') }}">Scénarios <x-icon name="arrow-right" /></a>@endcan
        </span>
    </li>
</ol>

<div class="grid grid-2 section-gap">
    <section class="card flush" data-tour="today">
        <div class="card-head">
            <h2><x-icon name="alarm-clock" /> À faire aujourd'hui</h2>
            <a class="btn btn-sm" href="{{ route('pm.tasks.index') }}">Toutes mes tâches</a>
        </div>
        @forelse ($myTasks as $task)
            @include('tasks.row', ['task' => $task])
        @empty
            <div class="empty-state"><x-icon name="circle-check" /><strong>Rien d'urgent</strong>Aucune tâche prévue aujourd'hui ni en retard.</div>
        @endforelse
        @if ($socialToDo)
            <div class="task-row">
                <div class="grow"><a class="t-title" href="{{ route('pm.social.index') }}">{{ $socialToDo }} profil(s) à contacter sur les réseaux sociaux</a></div>
                <x-icon name="share-2" />
            </div>
        @endif
    </section>

    <section class="card">
        <h2><x-icon name="history" /> Dernières activités</h2>
        @forelse ($recentActivities as $activity)
            @if ($loop->first)<ul class="timeline">@endif
            <li>
                <div class="meta">{{ $activity->occurred_at->format('d/m à H:i') }} · {{ $activity->user?->name ?? 'Système' }}
                    @if ($activity->subject) · <a href="{{ route('pm.companies.show', $activity->subject_id) }}">{{ $activity->subject->name }}</a>@endif
                </div>
                <div class="body">{{ \Illuminate\Support\Str::limit($activity->body, 110) }}</div>
            </li>
            @if ($loop->last)</ul>@endif
        @empty
            <div class="empty-state"><x-icon name="history" /><strong>Aucune activité</strong>Les créations, notes et changements de statut apparaîtront ici.</div>
        @endforelse
    </section>
</div>
@endsection
