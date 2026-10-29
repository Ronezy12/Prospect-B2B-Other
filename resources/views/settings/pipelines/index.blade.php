@extends('layouts.app')
@section('title', 'Secteurs et pipelines')

@section('content')
<div class="page-header">
    <div>
        <h1>Secteurs et pipelines</h1>
        <p class="subtitle">Chaque secteur possède sa pipeline commerciale et ses étapes.</p>
    </div>
</div>

<div class="grid grid-sidebar">
    <section class="card flush">
        <div class="card-head"><h2>Secteurs</h2></div>
        <div class="settings-list">
            @foreach ($sectors as $sector)
                <div class="settings-row">
                    <form method="POST" action="{{ route('pm.settings.sectors.update', $sector) }}" class="inline grow">
                        @csrf @method('PUT')
                        <label class="sr-only" for="sector-{{ $sector->id }}">Nom du secteur</label>
                        <input type="text" id="sector-{{ $sector->id }}" name="name" value="{{ $sector->name }}" maxlength="120" required>
                        <label class="checkbox small"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked($sector->is_active)> Actif</label>
                        <button type="submit" class="btn btn-sm">Enregistrer</button>
                    </form>
                    @if ($sector->pipeline)
                        <a class="btn btn-sm" href="{{ route('pm.settings.pipelines.edit', $sector->pipeline) }}"><x-icon name="kanban" /> Étapes de la pipeline</a>
                    @endif
                </div>
                @foreach ($sector->children as $child)
                    <div class="settings-row">
                        <form method="POST" action="{{ route('pm.settings.sectors.update', $child) }}" class="inline grow">
                            @csrf @method('PUT')
                            <span class="muted">↳</span>
                            <label class="sr-only" for="sector-{{ $child->id }}">Nom du sous-secteur</label>
                            <input type="text" id="sector-{{ $child->id }}" name="name" value="{{ $child->name }}" maxlength="120" required>
                            <label class="checkbox small"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked($child->is_active)> Actif</label>
                            <button type="submit" class="btn btn-sm">Enregistrer</button>
                        </form>
                    </div>
                @endforeach
            @endforeach
        </div>
        <form method="POST" action="{{ route('pm.settings.sectors.store') }}" class="inline-add">
            @csrf
            <label class="sr-only" for="new-sector">Nouveau secteur</label>
            <input type="text" id="new-sector" name="name" placeholder="Nouveau secteur ou sous-secteur" maxlength="120" required>
            <label class="sr-only" for="new-parent">Rattachement</label>
            <select id="new-parent" name="parent_id">
                <option value="">Secteur principal (avec sa pipeline)</option>
                @foreach ($sectors as $sector)<option value="{{ $sector->id }}">Sous-secteur de : {{ $sector->name }}</option>@endforeach
            </select>
            <button type="submit" class="btn btn-primary"><x-icon name="plus" /> Ajouter</button>
        </form>
    </section>

    <section class="card flush">
        <div class="card-head"><h2>Sources d'acquisition</h2></div>
        <div class="settings-list">
            @foreach ($sources as $source)
                <div class="settings-row">
                    <form method="POST" action="{{ route('pm.settings.sources.update', $source) }}" class="inline grow">
                        @csrf @method('PUT')
                        <label class="sr-only" for="source-{{ $source->id }}">Nom de la source</label>
                        <input type="text" id="source-{{ $source->id }}" name="name" value="{{ $source->name }}" maxlength="120" required>
                        <label class="checkbox small"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked($source->is_active)> Active</label>
                        <button type="submit" class="btn btn-sm" aria-label="Enregistrer"><x-icon name="check" /></button>
                    </form>
                </div>
            @endforeach
        </div>
        <form method="POST" action="{{ route('pm.settings.sources.store') }}" class="inline-add">
            @csrf
            <label class="sr-only" for="new-source">Nouvelle source</label>
            <input type="text" id="new-source" name="name" placeholder="Nouvelle source" maxlength="120" required>
            <button type="submit" class="btn btn-primary"><x-icon name="plus" /> Ajouter</button>
        </form>
    </section>
</div>
@endsection
