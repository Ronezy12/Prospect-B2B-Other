@extends('layouts.app')
@section('title', $opportunity->title)

@section('content')
@php($stages = $opportunity->pipeline->stages)
@php($currentIndex = $stages->search(fn ($s) => $s->id === $opportunity->stage_id))
<div class="page-header">
    <div>
        <div class="breadcrumb"><a href="{{ route('pm.pipelines.show', $opportunity->pipeline) }}">{{ $opportunity->pipeline->name }}</a> <x-icon name="chevron-right" /> Opportunité</div>
        <h1>{{ $opportunity->title }}</h1>
        <p class="subtitle">
            <a href="{{ route('pm.companies.show', $opportunity->company) }}">{{ $opportunity->company->name }}</a>
            · @include('partials.priority', ['priority' => $opportunity->priority])
            @if ($opportunity->closed_at) · clôturée le {{ $opportunity->closed_at->format('d/m/Y') }}@endif
        </p>
    </div>
    <div class="actions">
        @can('update', $opportunity)
            <a class="btn" href="{{ route('pm.tasks.create', ['opportunity_id' => $opportunity->id]) }}"><x-icon name="alarm-clock" /> Planifier une relance</a>
            <a class="btn" href="{{ route('pm.opportunities.edit', $opportunity) }}"><x-icon name="pencil" /> Modifier</a>
        @endcan
        @can('delete', $opportunity)
            <form method="POST" action="{{ route('pm.opportunities.destroy', $opportunity) }}" data-confirm="Supprimer cette opportunité ?">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-danger"><x-icon name="trash-2" /> Supprimer</button>
            </form>
        @endcan
    </div>
</div>

<div class="stage-track" aria-label="Progression">
    @foreach ($stages as $i => $s)
        <span @class(['passed' => $i < $currentIndex && $opportunity->stage->type !== 'lost', 'current' => $s->id === $opportunity->stage_id, 'lost' => $s->id === $opportunity->stage_id && $s->type === 'lost'])>{{ $s->name }}</span>
    @endforeach
</div>

<div class="grid grid-sidebar">
    <div class="stack">
        <section class="card">
            <h2>Détails</h2>
            <dl class="details">
                <dt>Étape</dt><dd>{{ $opportunity->stage->name }} ({{ $opportunity->stage->probability }} %)</dd>
                <dt>Prochaine action</dt><dd>{{ $opportunity->next_action ?? '—' }}</dd>
                <dt>Date de relance</dt><dd>@if ($opportunity->follow_up_at)<span @class(['badge', 'badge-danger' => $opportunity->isOverdue(), 'plain' => ! $opportunity->isOverdue()])>{{ $opportunity->follow_up_at->format('d/m/Y') }}</span>@else — @endif</dd>
                <dt>Montant estimé</dt><dd>{{ $opportunity->amount ? number_format((float) $opportunity->amount, 2, ',', ' ').' € HT' : '—' }}</dd>
                <dt>Interlocuteur</dt><dd>{{ $opportunity->contact?->fullName() ?? '—' }}</dd>
                <dt>Responsable</dt><dd>@if ($opportunity->owner)<span class="person"><x-avatar :user="$opportunity->owner" size="sm" /> {{ $opportunity->owner->name }}</span>@else Non assignée @endif</dd>
                @if ($opportunity->lost_reason)<dt>Motif de perte</dt><dd>{{ $opportunity->lost_reason }}</dd>@endif
            </dl>
        </section>

        <section class="card flush" id="taches">
            <div class="card-head">
                <h2>Tâches et relances</h2>
                @can('update', $opportunity)<a class="btn btn-sm" href="{{ route('pm.tasks.create', ['opportunity_id' => $opportunity->id]) }}"><x-icon name="plus" /> Ajouter</a>@endcan
            </div>
            @forelse ($tasks as $task)
                @include('tasks.row', ['task' => $task, 'showContext' => false])
            @empty
                <div class="empty-state"><x-icon name="list-checks" /><strong>Aucune tâche</strong>Planifiez la prochaine relance pour ne pas laisser filer l'opportunité.</div>
            @endforelse
        </section>
    </div>

    <div class="stack">
        @can('update', $opportunity)
            <section class="card">
                <h2>Changer d'étape</h2>
                <form method="POST" action="{{ route('pm.opportunities.move', $opportunity) }}">
                    @csrf
                    <div class="field">
                        <label for="stage_id">Nouvelle étape</label>
                        <select id="stage_id" name="stage_id">
                            @foreach ($stages as $s)<option value="{{ $s->id }}" @selected($s->id === $opportunity->stage_id)>{{ $s->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="form-actions"><button type="submit" class="btn btn-primary"><x-icon name="arrow-right-left" /> Déplacer</button></div>
                </form>
            </section>
        @endcan

        <section class="card">
            <h2><x-icon name="history" /> Historique des étapes</h2>
            <ul class="timeline">
                @forelse ($opportunity->history as $h)
                    <li>
                        <div class="meta">{{ $h->changed_at->format('d/m/Y à H:i') }} · {{ $h->user?->name ?? 'Système' }}</div>
                        <div class="body">{{ $h->from_name ? $h->from_name.' → '.$h->to_name : 'Créée à l\'étape '.$h->to_name }}</div>
                    </li>
                @empty
                    <li class="muted">Aucun changement.</li>
                @endforelse
            </ul>
        </section>
    </div>
</div>
@endsection
