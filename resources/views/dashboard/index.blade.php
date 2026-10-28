@extends('layouts.app')
@section('title', 'Tableau de bord')

@section('content')
<div class="page-header">
    <h1>Tableau de bord</h1>
    <div class="actions">
        @can('prospects.create')
            <a class="btn btn-primary" href="{{ route('pm.companies.create') }}">Nouveau prospect</a>
        @endcan
        @can('prospects.import')
            <a class="btn" href="{{ route('pm.imports.create') }}">Importer un fichier</a>
        @endcan
    </div>
</div>

<div class="stats">
    <div class="stat"><div class="value">{{ number_format($totals['prospects'], 0, ',', ' ') }}</div><div class="label">Prospects dans votre périmètre</div></div>
    <div class="stat"><div class="value">{{ number_format($totals['new_month'], 0, ',', ' ') }}</div><div class="label">Nouveaux ce mois-ci</div></div>
    <div class="stat"><div class="value">{{ number_format($totals['unassigned'], 0, ',', ' ') }}</div><div class="label">Sans responsable</div></div>
    <div class="stat"><div class="value">{{ number_format($totals['contacts'], 0, ',', ' ') }}</div><div class="label">Contacts</div></div>
    <div class="stat"><div class="value">{{ number_format($totals['clients'], 0, ',', ' ') }}</div><div class="label">Convertis en clients</div></div>
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
                        <span>{{ $n }}</span>
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
                        <span>{{ $n }}</span>
                        <progress max="{{ $totals['prospects'] }}" value="{{ $n }}" aria-label="{{ $sector }}"></progress>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>

<div class="grid grid-2">
    <section class="card">
        <h2>Activité récente</h2>
        @forelse ($recentActivities as $activity)
            @if ($loop->first)<ul class="timeline">@endif
            <li>
                <div class="meta">{{ $activity->occurred_at->format('d/m/Y H:i') }} · {{ $activity->user?->name ?? 'Système' }}
                    @if ($activity->subject) · <a href="{{ route('pm.companies.show', $activity->subject_id) }}">{{ $activity->subject->name }}</a>@endif
                </div>
                <div class="body">{{ \Illuminate\Support\Str::limit($activity->body, 160) }}</div>
            </li>
            @if ($loop->last)</ul>@endif
        @empty
            <p class="muted">Aucune activité enregistrée.</p>
        @endforelse
    </section>

    @can('prospects.import')
    <section class="card">
        <h2>Mes derniers imports</h2>
        @forelse ($recentImports as $import)
            @if ($loop->first)<ul class="timeline">@endif
            <li>
                <a href="{{ route('pm.imports.show', $import) }}">{{ $import->original_name }}</a>
                <div class="meta">{{ $import->created_at->format('d/m/Y H:i') }} · {{ $import->statusLabel() }} · {{ $import->created_count }} créé(s), {{ $import->error_count }} erreur(s)</div>
            </li>
            @if ($loop->last)</ul>@endif
        @empty
            <p class="muted">Aucun import.</p>
        @endforelse
    </section>
    @endcan
</div>
@endsection
