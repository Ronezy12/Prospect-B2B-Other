@extends('layouts.app')
@section('title', 'Opportunités')

@section('content')
<div class="page-header">
    <div>
        <h1>Pipelines commerciales</h1>
        <p class="subtitle">{{ number_format($opportunities->total(), 0, ',', ' ') }} opportunité(s) · toutes pipelines</p>
    </div>
</div>

@include('partials.pipeline-tabs')

<form method="GET" class="card" role="search">
    <div class="filters">
        <div class="field wide">
            <label for="q">Recherche</label>
            <div class="searchbox"><x-icon name="search" /><input type="search" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Opportunité ou entreprise…"></div>
        </div>
        <div class="field">
            <label for="pipeline_id">Pipeline</label>
            <select id="pipeline_id" name="pipeline_id">
                <option value="">Toutes</option>
                @foreach ($pipelines as $p)<option value="{{ $p->id }}" @selected(($filters['pipeline_id'] ?? '') == $p->id)>{{ $p->name }}</option>@endforeach
            </select>
        </div>
        <div class="field">
            <label for="stage_id">Étape</label>
            <select id="stage_id" name="stage_id">
                <option value="">Toutes</option>
                @foreach ($pipelines as $p)
                    <optgroup label="{{ $p->name }}">
                        @foreach ($p->stages as $s)<option value="{{ $s->id }}" @selected(($filters['stage_id'] ?? '') == $s->id)>{{ $s->name }}</option>@endforeach
                    </optgroup>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="state">État</label>
            <select id="state" name="state">
                <option value="open" @selected(($filters['state'] ?? '') === 'open')>En cours</option>
                <option value="overdue" @selected(($filters['state'] ?? '') === 'overdue')>Relance en retard</option>
                <option value="closed" @selected(($filters['state'] ?? '') === 'closed')>Clôturées</option>
                <option value="all" @selected(request('state') === 'all')>Toutes</option>
            </select>
        </div>
        <div class="field">
            <label for="owner_id">Responsable</label>
            <select id="owner_id" name="owner_id">
                <option value="">Tous</option>
                <option value="none" @selected(($filters['owner_id'] ?? '') === 'none')>Non assignées</option>
                @foreach ($users as $user)<option value="{{ $user->id }}" @selected(($filters['owner_id'] ?? '') == $user->id)>{{ $user->name }}</option>@endforeach
            </select>
        </div>
        <div class="field">
            <label for="priority">Priorité</label>
            <select id="priority" name="priority">
                <option value="">Toutes</option>
                @foreach (config('prospection.priorities') as $key => $label)<option value="{{ $key }}" @selected(($filters['priority'] ?? '') === $key)>{{ $label }}</option>@endforeach
            </select>
        </div>
        <div class="field buttons">
            <button type="submit" class="btn btn-primary"><x-icon name="filter" /> Filtrer</button>
            <a class="btn btn-ghost" href="{{ route('pm.opportunities.index') }}">Réinitialiser</a>
        </div>
    </div>
</form>

<div class="card flush">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Opportunité</th><th>Pipeline · étape</th><th>Priorité</th><th>Prochaine action</th><th>Relance</th><th>Montant</th><th>Responsable</th></tr></thead>
            <tbody>
            @forelse ($opportunities as $o)
                <tr>
                    <td><a class="row-title" href="{{ route('pm.opportunities.show', $o) }}">{{ $o->title }}</a><div class="sub">{{ $o->company->name }}@if ($o->company->city) · {{ $o->company->city }}@endif</div></td>
                    <td>{{ $o->pipeline->name }}<div><span @class(['badge', 'badge-success' => $o->stage->type === 'won', 'badge-danger' => $o->stage->type === 'lost', 'badge-primary' => $o->stage->type === 'open'])>{{ $o->stage->name }}</span></div></td>
                    <td>@include('partials.priority', ['priority' => $o->priority])</td>
                    <td class="small">{{ \Illuminate\Support\Str::limit($o->next_action, 70) ?: '—' }}</td>
                    <td class="nowrap">@if ($o->follow_up_at)<span @class(['badge', 'badge-danger' => $o->isOverdue(), 'plain' => ! $o->isOverdue()])>{{ $o->follow_up_at->format('d/m/Y') }}</span>@else — @endif</td>
                    <td class="num nowrap">{{ $o->amount ? number_format((float) $o->amount, 0, ',', ' ').' €' : '—' }}</td>
                    <td>@if ($o->owner)<span class="person"><x-avatar :user="$o->owner" size="sm" /> {{ $o->owner->name }}</span>@else<span class="muted">Non assignée</span>@endif</td>
                </tr>
            @empty
                <tr><td colspan="7" class="table-empty"><div class="empty-state"><x-icon name="briefcase" /><strong>Aucune opportunité</strong>Créez une opportunité depuis la fiche d'un prospect.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $opportunities->links('partials.pagination') }}
</div>
@endsection
