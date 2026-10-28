@extends('layouts.app')
@section('title', 'Import '.$import->original_name)

@section('content')
<div class="page-header" @unless ($import->isFinished()) data-refresh-seconds="5" @endunless>
    <div>
        <div class="breadcrumb"><a href="{{ route('pm.imports.index') }}">Imports</a></div>
        <h1>{{ $import->original_name }}</h1>
    </div>
    <div class="actions">
        @if (in_array($import->status, ['uploaded', 'mapped']))
            @can('update', $import)<a class="btn btn-primary" href="{{ route('pm.imports.mapping', $import) }}">Définir la correspondance</a>@endcan
        @endif
        @if (! $import->isFinished())
            @can('update', $import)
                <form method="POST" action="{{ route('pm.imports.cancel', $import) }}" data-confirm="Annuler cet import ?">
                    @csrf
                    <button type="submit" class="btn btn-danger">Annuler l'import</button>
                </form>
            @endcan
        @endif
        @if ($import->processed_rows > 0)
            <a class="btn" href="{{ route('pm.imports.report', $import) }}"><x-icon name="download" /> Rapport des anomalies</a>
        @endif
    </div>
</div>

@if ($import->failure_message)
    <div class="alert alert-danger">{{ $import->failure_message }}</div>
@endif

<div class="stats">
    <div class="stat"><div class="value">{{ $import->statusLabel() }}</div><div class="label">Statut</div></div>
    <div class="stat"><div class="value">{{ $import->processed_rows }} / {{ $import->total_rows }}</div><div class="label">Lignes traitées</div></div>
    <div class="stat"><div class="value">{{ $import->created_count }}</div><div class="label">Créées</div></div>
    <div class="stat"><div class="value">{{ $import->updated_count }}</div><div class="label">Mises à jour</div></div>
    <div class="stat"><div class="value">{{ $import->skipped_count }}</div><div class="label">Ignorées</div></div>
    <div class="stat"><div class="value">{{ $import->error_count }}</div><div class="label">Erreurs</div></div>
</div>
@unless ($import->isFinished())
    <progress class="import-progress" max="{{ max(1, $import->total_rows) }}" value="{{ $import->processed_rows }}" aria-label="Progression"></progress>
    <p class="small muted">Cette page se met à jour automatiquement.</p>
@endunless

<div class="card flush">
    <form method="GET" class="bulk-bar">
        <label for="result">Afficher</label>
        <select id="result" name="result">
            <option value="">Toutes les lignes</option>
            @foreach (\App\Models\ImportRow::RESULT_LABELS as $key => $label)
                <option value="{{ $key }}" @selected($result === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-sm">Filtrer</button>
    </form>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Ligne</th><th>Résultat</th><th>Fiche</th><th>Messages</th></tr></thead>
            <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row->row_number }}</td>
                    <td><span @class(['badge', 'badge-success' => $row->result === 'created', 'badge-primary' => $row->result === 'updated', 'badge-danger' => $row->result === 'error', 'badge-warning' => str_starts_with($row->result, 'skipped')])>{{ \App\Models\ImportRow::RESULT_LABELS[$row->result] ?? $row->result }}</span></td>
                    <td>@if ($row->company && ! $row->company->trashed())<a href="{{ route('pm.companies.show', $row->company) }}">{{ $row->company->name }}</a>@else — @endif</td>
                    <td class="small">@foreach ((array) $row->messages as $message)<div>{{ $message }}</div>@endforeach</td>
                </tr>
            @empty
                <tr><td colspan="4" class="table-empty">Aucune ligne traitée pour le moment.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $rows->links('partials.pagination') }}
</div>
@endsection
