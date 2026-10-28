@extends('layouts.app')
@section('title', 'Tableau de bord')

@section('content')
<section class="hero">
    <div>
        <div class="eyebrow">{{ now()->locale('fr')->isoFormat('dddd D MMMM YYYY') }}</div>
        <h1>Bonjour {{ \Illuminate\Support\Str::before(auth()->user()->name, ' ') }}</h1>
        <p>
            @if ($totals['prospects'] === 0)
                Votre base est vide pour l'instant. Commencez par créer un prospect ou importer un fichier.
            @else
                {{ number_format($totals['prospects'], 0, ',', ' ') }} prospects dans votre périmètre, dont {{ $totals['new_month'] }} ajoutés ce mois-ci{{ $totals['unassigned'] > 0 ? ' et '.$totals['unassigned'].' sans responsable' : '' }}.
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

<div class="stats">
    <div class="stat"><span class="stat-icon"><x-icon name="building-2" /></span><div><div class="value">{{ number_format($totals['prospects'], 0, ',', ' ') }}</div><div class="label">Prospects</div></div></div>
    <div class="stat"><span class="stat-icon pink"><x-icon name="sparkles" /></span><div><div class="value">{{ number_format($totals['new_month'], 0, ',', ' ') }}</div><div class="label">Nouveaux ce mois-ci</div></div></div>
    <div class="stat"><span class="stat-icon orange"><x-icon name="user-plus" /></span><div><div class="value">{{ number_format($totals['unassigned'], 0, ',', ' ') }}</div><div class="label">Sans responsable</div></div></div>
    <div class="stat"><span class="stat-icon blue"><x-icon name="contact-round" /></span><div><div class="value">{{ number_format($totals['contacts'], 0, ',', ' ') }}</div><div class="label">Interlocuteurs</div></div></div>
    <div class="stat"><span class="stat-icon green"><x-icon name="trending-up" /></span><div><div class="value">{{ number_format($totals['clients'], 0, ',', ' ') }}</div><div class="label">Convertis en clients</div></div></div>
</div>

<div class="grid grid-2">
    <section class="card">
        <h2>Prospects par statut</h2>
        @if ($totals['prospects'] === 0)
            <p class="muted">Aucun prospect pour le moment.</p>
        @else
            <ul class="bar-list">
                @foreach ($statuses as $key => $label)
                    @php($n = (int) ($byStatus[$key] ?? 0))
                    <li>
                        <a href="{{ route('pm.companies.index', ['status' => $key]) }}">{{ $label }}</a>
                        <span class="val">{{ $n }}</span>
                        <progress max="{{ $totals['prospects'] }}" value="{{ $n }}" aria-label="{{ $label }}"></progress>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="card">
        <h2>Prospects par secteur</h2>
        @if ($bySector->isEmpty())
            <p class="muted">Aucune donnée.</p>
        @else
            <ul class="bar-list">
                @foreach ($bySector as $sector => $n)
                    <li>
                        <span>{{ $sector }}</span>
                        <span class="val">{{ $n }}</span>
                        <progress max="{{ $totals['prospects'] }}" value="{{ $n }}" aria-label="{{ $sector }}"></progress>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>

<div class="grid grid-2 section-gap">
    <section class="card">
        <h2>Activité récente</h2>
        @forelse ($recentActivities as $activity)
            @if ($loop->first)<ul class="timeline">@endif
            <li>
                <div class="meta">{{ $activity->occurred_at->format('d/m/Y à H:i') }} · {{ $activity->user?->name ?? 'Système' }}
                    @if ($activity->subject) · <a href="{{ route('pm.companies.show', $activity->subject_id) }}">{{ $activity->subject->name }}</a>@endif
                </div>
                <div class="body">{{ \Illuminate\Support\Str::limit($activity->body, 160) }}</div>
            </li>
            @if ($loop->last)</ul>@endif
        @empty
            <div class="empty-state"><x-icon name="history" /><strong>Aucune activité</strong>Les créations, notes et changements de statut apparaîtront ici.</div>
        @endforelse
    </section>

    @can('prospects.import')
    <section class="card">
        <h2>Mes derniers imports</h2>
        @forelse ($recentImports as $import)
            @if ($loop->first)<ul class="timeline">@endif
            <li>
                <a href="{{ route('pm.imports.show', $import) }}"><strong>{{ $import->original_name }}</strong></a>
                <div class="meta">{{ $import->created_at->format('d/m/Y à H:i') }} · {{ $import->statusLabel() }} · {{ $import->created_count }} créé(s), {{ $import->error_count }} erreur(s)</div>
            </li>
            @if ($loop->last)</ul>@endif
        @empty
            <div class="empty-state"><x-icon name="file-spreadsheet" /><strong>Aucun import</strong>Importez un fichier CSV ou Excel pour remplir votre base.</div>
        @endforelse
    </section>
    @endcan
</div>
@endsection
