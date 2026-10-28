@extends('layouts.app')
@section('title', 'Imports')

@section('content')
<div class="page-header">
    <div>
        <h1>Imports</h1>
        <p class="subtitle">Historique des fichiers importés et rapports de traitement</p>
    </div>
    <div class="actions"><a class="btn btn-primary" href="{{ route('pm.imports.create') }}"><x-icon name="upload" /> Nouvel import</a></div>
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
                    <td><a class="row-title" href="{{ route('pm.imports.show', $import) }}"><x-icon name="file-spreadsheet" /> {{ $import->original_name }}</a></td>
                    <td>@if ($import->user)<span class="person"><x-avatar :user="$import->user" size="sm" /> {{ $import->user->name }}</span>@else — @endif</td>
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
                <tr><td colspan="9" class="table-empty"><div class="empty-state"><x-icon name="file-spreadsheet" /><strong>Aucun import pour le moment</strong>Importez un fichier CSV ou Excel pour remplir votre base.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $imports->links('partials.pagination') }}
</div>
@endsection
