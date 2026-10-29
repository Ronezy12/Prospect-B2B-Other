@extends('layouts.app')
@section('title', 'Automatisations')

@section('content')
<div class="page-header">
    <div>
        <h1>Automatisations</h1>
        <p class="subtitle">Des règles « Quand… alors… » qui font les tâches répétitives à votre place. Exemple : quand un prospect est créé, alors créer une tâche « Appeler ».</p>
    </div>
    <form method="POST" action="{{ route('pm.automations.store') }}" class="actions">@csrf<button type="submit" class="btn btn-primary"><x-icon name="plus" /> Scénario vierge</button></form>
</div>

<div class="card flush">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Scénario</th><th>Déclencheur</th><th>Statut</th><th>Version active</th><th>En attente</th><th>Terminées</th><th>Erreurs</th></tr></thead>
            <tbody>
            @forelse ($automations as $a)
                <tr>
                    <td><a class="row-title" href="{{ route('pm.automations.show', $a) }}">{{ $a->name }}</a></td>
                    <td class="small">{{ $a->triggerLabel() }}</td>
                    <td><span @class(['badge', 'badge-success' => $a->status === 'active', 'badge-warning' => $a->status === 'en_pause', 'badge-danger' => $a->status === 'arretee'])>{{ $a->statusLabel() }}</span></td>
                    <td class="num">{{ $a->activeVersion ? 'v'.$a->activeVersion->version : '—' }}</td>
                    <td class="num">{{ $a->waiting_count }}</td>
                    <td class="num">{{ $a->done_count }}</td>
                    <td class="num">{{ $a->error_count }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="table-empty"><div class="empty-state"><x-icon name="sparkles" /><strong>Aucun scénario</strong>Partez d'un modèle ci-dessous.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<section class="card">
    <h2>Partir d'un modèle</h2>
    <p class="small muted">Le scénario est créé en brouillon : vous pouvez l'ajuster et le tester sur une fiche avant de l'activer.</p>
    <div class="preset-grid">
        @foreach ($presets as $key => $preset)
            <form method="POST" action="{{ route('pm.automations.store') }}" class="preset">
                @csrf
                <input type="hidden" name="preset" value="{{ $key }}">
                <strong>{{ $preset['name'] }}</strong>
                <span class="small muted">{{ config('prospection.automation.triggers')[$preset['trigger']] }} · {{ count($preset['steps']) }} étape(s)</span>
                <button type="submit" class="btn btn-sm"><x-icon name="plus" /> Utiliser</button>
            </form>
        @endforeach
    </div>
</section>
@endsection
