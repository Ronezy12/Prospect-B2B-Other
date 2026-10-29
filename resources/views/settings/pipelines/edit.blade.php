@extends('layouts.app')
@section('title', 'Pipeline '.$pipeline->name)

@section('content')
<div class="page-header">
    <div>
        <div class="breadcrumb"><a href="{{ route('pm.settings.pipelines.index') }}">Secteurs et pipelines</a> <x-icon name="chevron-right" /> {{ $pipeline->sector->name }}</div>
        <h1>Pipeline « {{ $pipeline->name }} »</h1>
        <p class="subtitle">Les étapes « Gagné » et « Perdu » clôturent l'opportunité. Les noms sont figés dans l'historique.</p>
    </div>
    <div class="actions"><a class="btn" href="{{ route('pm.pipelines.show', $pipeline) }}"><x-icon name="kanban" /> Voir le Kanban</a></div>
</div>

<form method="POST" action="{{ route('pm.settings.pipelines.update', $pipeline) }}" class="card">
    @csrf @method('PUT')
    <div class="toolbar">
        <div class="field grow">
            <label for="name">Nom de la pipeline</label>
            <input type="text" id="name" name="name" value="{{ old('name', $pipeline->name) }}" maxlength="120" required>
        </div>
        <label class="checkbox"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked($pipeline->is_active)> Pipeline active</label>
        <button type="submit" class="btn btn-primary"><x-icon name="check" /> Enregistrer</button>
    </div>
</form>

<section class="card flush">
    <div class="card-head"><h2>Étapes <span class="muted">· {{ $pipeline->stages->count() }}</span></h2></div>
    <div class="settings-list">
        @foreach ($pipeline->stages as $stage)
            <div class="settings-row">
                <div class="inline">
                    <form method="POST" action="{{ route('pm.settings.stages.move', $stage) }}">@csrf<input type="hidden" name="direction" value="up"><button type="submit" class="icon-btn" @disabled($loop->first) aria-label="Monter {{ $stage->name }}">↑</button></form>
                    <form method="POST" action="{{ route('pm.settings.stages.move', $stage) }}">@csrf<input type="hidden" name="direction" value="down"><button type="submit" class="icon-btn" @disabled($loop->last) aria-label="Descendre {{ $stage->name }}">↓</button></form>
                </div>
                <form method="POST" action="{{ route('pm.settings.stages.update', $stage) }}" class="inline grow">
                    @csrf @method('PUT')
                    <label class="sr-only" for="stage-{{ $stage->id }}">Nom</label>
                    <input type="text" id="stage-{{ $stage->id }}" name="name" value="{{ $stage->name }}" maxlength="80" required>
                    <label class="sr-only" for="type-{{ $stage->id }}">Type</label>
                    <select id="type-{{ $stage->id }}" name="type" @disabled($stage->opportunities_count > 0)>
                        @foreach (config('prospection.stage_types') as $key => $label)<option value="{{ $key }}" @selected($stage->type === $key)>{{ $label }}</option>@endforeach
                    </select>
                    @if ($stage->opportunities_count > 0)<input type="hidden" name="type" value="{{ $stage->type }}">@endif
                    <label class="sr-only" for="prob-{{ $stage->id }}">Probabilité</label>
                    <input type="number" id="prob-{{ $stage->id }}" name="probability" value="{{ $stage->probability }}" min="0" max="100" title="Probabilité de gain (%)"> <span class="small muted">%</span>
                    <button type="submit" class="btn btn-sm">Enregistrer</button>
                </form>
                <span class="badge plain">{{ $stage->opportunities_count }} opportunité(s)</span>
                @if ($stage->opportunities_count === 0)
                    <form method="POST" action="{{ route('pm.settings.stages.destroy', $stage) }}" data-confirm="Supprimer l'étape « {{ $stage->name }} » ?">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn-link danger small">Supprimer</button>
                    </form>
                @endif
            </div>
        @endforeach
    </div>
    <form method="POST" action="{{ route('pm.settings.stages.store', $pipeline) }}" class="inline-add">
        @csrf
        <label class="sr-only" for="new-stage">Nouvelle étape</label>
        <input type="text" id="new-stage" name="name" placeholder="Nouvelle étape" maxlength="80" required>
        <label class="sr-only" for="new-type">Type</label>
        <select id="new-type" name="type">@foreach (config('prospection.stage_types') as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select>
        <label class="sr-only" for="new-prob">Probabilité</label>
        <input type="number" id="new-prob" name="probability" value="30" min="0" max="100">
        <button type="submit" class="btn btn-primary"><x-icon name="plus" /> Ajouter l'étape</button>
    </form>
</section>
@endsection
