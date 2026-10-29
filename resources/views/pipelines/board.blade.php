@extends('layouts.app')
@section('title', 'Pipeline '.$pipeline->name)

@section('content')
<div class="page-header">
    <div>
        <h1>Pipelines commerciales</h1>
        <p class="subtitle">{{ $pipeline->name }} · {{ $columns->sum('count') }} opportunité(s) dans votre périmètre</p>
    </div>
    <div class="actions">
        @can('pipelines.manage')
            <a class="btn" href="{{ route('pm.settings.pipelines.edit', $pipeline) }}"><x-icon name="settings" /> Configurer les étapes</a>
        @endcan
    </div>
</div>

@include('partials.pipeline-tabs')

<form method="GET" class="toolbar" role="search">
    <div class="searchbox"><x-icon name="search" /><input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Rechercher une opportunité ou une entreprise…" aria-label="Recherche"></div>
    <select name="owner_id" aria-label="Responsable">
        <option value="">Tous les responsables</option>
        <option value="none" @selected(($filters['owner_id'] ?? '') === 'none')>Non assignées</option>
        @foreach ($users as $user)<option value="{{ $user->id }}" @selected(($filters['owner_id'] ?? '') == $user->id)>{{ $user->name }}</option>@endforeach
    </select>
    <select name="priority" aria-label="Priorité">
        <option value="">Toutes priorités</option>
        @foreach (config('prospection.priorities') as $key => $label)<option value="{{ $key }}" @selected(($filters['priority'] ?? '') === $key)>{{ $label }}</option>@endforeach
    </select>
    <button type="submit" class="btn"><x-icon name="filter" /> Filtrer</button>
    @if ($filters)<a class="btn btn-ghost" href="{{ route('pm.pipelines.show', $pipeline) }}">Réinitialiser</a>@endif
</form>

<div class="alert" data-board-notice role="status" hidden></div>
@if ($canMove)
    <p class="small muted">Glissez une carte vers une autre colonne pour changer son étape. Sur mobile, utilisez le menu « Déplacer vers » de chaque carte.</p>
@endif

<div class="board" data-board>
    @foreach ($columns as $column)
        @php($stage = $column['stage'])
        <section @class(['column', $stage->type]) aria-label="Étape {{ $stage->name }}">
            <header class="column-head">
                <div class="title"><span class="dot"></span> {{ $stage->name }} <span class="n" data-count>{{ $column['count'] }}</span></div>
                <div class="meta">{{ $stage->probability }} %@if ($column['amount'] > 0) · {{ number_format($column['amount'], 0, ',', ' ') }} € @endif</div>
            </header>
            <div class="column-body" data-stage-id="{{ $stage->id }}">
                @foreach ($column['cards'] as $deal)
                    <article class="deal" draggable="{{ $canMove ? 'true' : 'false' }}" data-id="{{ $deal->id }}" data-title="{{ $deal->title }}" data-move-url="{{ route('pm.opportunities.move', $deal) }}">
                        <a class="deal-title" href="{{ route('pm.opportunities.show', $deal) }}">{{ $deal->title }}</a>
                        <div class="deal-company">{{ $deal->company->name }}@if ($deal->company->city) · {{ $deal->company->city }}@endif</div>
                        @if ($deal->next_action)<div class="next">{{ \Illuminate\Support\Str::limit($deal->next_action, 80) }}</div>@endif
                        <div class="deal-foot">
                            @include('partials.priority', ['priority' => $deal->priority])
                            @if ($deal->follow_up_at)
                                <span @class(['follow', 'late' => $deal->isOverdue()]) title="Date de relance"><x-icon name="alarm-clock" /> {{ $deal->follow_up_at->format('d/m') }}</span>
                            @endif
                            <span class="grow"></span>
                            @if ($deal->amount)<span class="amount">{{ number_format((float) $deal->amount, 0, ',', ' ') }} €</span>@endif
                            @if ($deal->owner)<x-avatar :user="$deal->owner" size="sm" />@endif
                        </div>
                        @if ($canMove)
                            <form method="POST" action="{{ route('pm.opportunities.move', $deal) }}" class="deal-move">
                                @csrf
                                <label class="sr-only" for="move-{{ $deal->id }}">Déplacer vers</label>
                                <select id="move-{{ $deal->id }}" name="stage_id">
                                    @foreach ($columns as $c)<option value="{{ $c['stage']->id }}" @selected($c['stage']->id === $stage->id)>{{ $c['stage']->name }}</option>@endforeach
                                </select>
                                <button type="submit" class="btn btn-sm">Déplacer</button>
                            </form>
                        @endif
                    </article>
                @endforeach
            </div>
            @if ($column['count'] > $perColumn)
                <a class="column-more" href="{{ route('pm.opportunities.index', ['pipeline_id' => $pipeline->id, 'stage_id' => $stage->id, 'state' => 'all']) }}">+ {{ $column['count'] - $perColumn }} autre(s) en vue liste</a>
            @endif
        </section>
    @endforeach
</div>
@endsection
