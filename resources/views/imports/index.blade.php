@extends('layouts.app')
@section('title', 'Imports')

@section('content')
<div class="page-header">
    <h1>Imports et historique</h1>
    <div class="actions"><a class="btn btn-primary" href="{{ route('pm.imports.create') }}">Nouvel import</a></div>
</div>

<div class="card flush">
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr><th>Fichier</th><th>Par</th><th>Date</th><th>Statut</th><th>Lignes</th><th>Créés</th><th>Mis à jour</th><th>Ignorés</th><th>Erreurs</th></tr>
            </thead>
            <tbody>
            @forelse ($imports as $import)
                <tr>
                    <td><a href="{{ route('pm.imports.show', $import) }}">{{ $import->original_name }}</a></td>
                    <td>{{ $import->user?->name ?? '—' }}</td>
                    <td class="nowrap">{{ $import->created_at->format('d/m/Y H:i') }}</td>
                    <td>
                        <span @class(['badge', 'badge-success' => $import->status === 'completed', 'badge-danger' => $import->status === 'failed', 'badge-warning' => in_array($import->status, ['queued', 'processing'])])>{{ $import->statusLabel() }}</span>
                    </td>
                    <td>{{ $import->total_rows }}</td>
                    <td>{{ $import->created_count }}</td>
                    <td>{{ $import->updated_count }}</td>
                    <td>{{ $import->skipped_count }}</td>
                    <td>{{ $import->error_count }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="table-empty">Aucun import pour le moment.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $imports->links('partials.pagination') }}
</div>
@endsection
