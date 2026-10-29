@extends('layouts.app')
@section('title', $segment->exists ? $segment->name : 'Nouvelle liste de destinataires')

@section('content')
@php($f = $segment->filters ?? [])
<div class="page-header">
    <div>
        <div class="breadcrumb"><a href="{{ route('pm.segments.index') }}">Listes de destinataires</a></div>
        <h1>{{ $segment->exists ? $segment->name : 'Nouvelle liste de destinataires' }}</h1>
    </div>
    @if ($segment->exists)
        <form method="POST" action="{{ route('pm.segments.destroy', $segment) }}" data-confirm="Supprimer cette liste ?">@csrf @method('DELETE')<button type="submit" class="btn btn-danger"><x-icon name="trash-2" /> Supprimer</button></form>
    @endif
</div>

@if ($estimate)
    <div class="stats">
        <div class="stat"><span class="stat-icon"><x-icon name="users" /></span><div><div class="value">{{ $estimate['total'] }}</div><div class="label">Contacts avec e-mail ciblés</div></div></div>
        <div class="stat"><span class="stat-icon green"><x-icon name="circle-check" /></span><div><div class="value">{{ $estimate['eligible'] }}</div><div class="label">Autorisés en prospection</div></div></div>
        <div class="stat"><span class="stat-icon pink"><x-icon name="shield-check" /></span><div><div class="value">{{ array_sum($estimate['excluded']) }}</div><div class="label">Exclus par la conformité</div></div></div>
    </div>
    @if ($estimate['excluded'])
        <p class="small muted">Exclusions : @foreach ($estimate['excluded'] as $reason => $n){{ config('prospection.exclusion_reasons')[$reason] ?? $reason }} ({{ $n }})@if (! $loop->last), @endif @endforeach</p>
    @endif
@endif

<form method="POST" action="{{ $segment->exists ? route('pm.segments.update', $segment) : route('pm.segments.store') }}" class="card">
    @csrf
    @if ($segment->exists) @method('PUT') @endif
    <div class="form-grid">
        <div class="field full"><label for="name">Nom de la liste *</label><input type="text" id="name" name="name" value="{{ old('name', $segment->name) }}" required maxlength="150" placeholder="ex. Agences immobilières du Rhône"></div>
        <div class="field">
            <label for="f-lifecycle">Fiches</label>
            <select id="f-lifecycle" name="filters[lifecycle]">
                @foreach (['prospect' => 'Prospects uniquement', 'client' => 'Clients uniquement', 'all' => 'Prospects et clients'] as $k => $l)<option value="{{ $k }}" @selected(($f['lifecycle'] ?? 'prospect') === $k)>{{ $l }}</option>@endforeach
            </select>
        </div>
        <div class="field">
            <label for="f-sector">Secteur</label>
            <select id="f-sector" name="filters[sector_id]">
                <option value="">Tous</option>
                @foreach ($sectors as $s)<option value="{{ $s->id }}" @selected(($f['sector_id'] ?? '') == $s->id)>{{ $s->name }}</option>@foreach ($s->children as $c)<option value="{{ $c->id }}" @selected(($f['sector_id'] ?? '') == $c->id)>— {{ $c->name }}</option>@endforeach @endforeach
            </select>
        </div>
        <div class="field">
            <label for="f-status">Statut commercial</label>
            <select id="f-status" name="filters[status]"><option value="">Tous</option>@foreach (config('prospection.company_statuses') as $k => $l)<option value="{{ $k }}" @selected(($f['status'] ?? '') === $k)>{{ $l }}</option>@endforeach</select>
        </div>
        <div class="field">
            <label for="f-source">Source</label>
            <select id="f-source" name="filters[source_id]"><option value="">Toutes</option>@foreach ($sources as $s)<option value="{{ $s->id }}" @selected(($f['source_id'] ?? '') == $s->id)>{{ $s->name }}</option>@endforeach</select>
        </div>
        <div class="field"><label for="f-city">Ville</label><input type="text" id="f-city" name="filters[city]" value="{{ $f['city'] ?? '' }}" maxlength="120"></div>
        <div class="field"><label for="f-postal">Code postal / département</label><input type="text" id="f-postal" name="filters[postal_code]" value="{{ $f['postal_code'] ?? '' }}" maxlength="12" placeholder="ex. 69"></div>
        <div class="field">
            <label for="f-tag">Tag</label>
            <select id="f-tag" name="filters[tag_id]"><option value="">Tous</option>@foreach ($tags as $t)<option value="{{ $t->id }}" @selected(($f['tag_id'] ?? '') == $t->id)>{{ $t->name }}</option>@endforeach</select>
        </div>
        <div class="field">
            <label for="f-owner">Responsable</label>
            <select id="f-owner" name="filters[owner_id]"><option value="">Tous</option>@foreach ($users as $u)<option value="{{ $u->id }}" @selected(($f['owner_id'] ?? '') == $u->id)>{{ $u->name }}</option>@endforeach</select>
        </div>
        <div class="field">
            <label for="f-audience">Type de contact</label>
            <select id="f-audience" name="filters[audience_type]"><option value="">Tous</option>@foreach (config('prospection.audience_types') as $k => $l)<option value="{{ $k }}" @selected(($f['audience_type'] ?? '') === $k)>{{ $l }}</option>@endforeach</select>
        </div>
        <div class="field">
            <label for="f-basis">Base légale</label>
            <select id="f-basis" name="filters[legal_basis]"><option value="">Toutes</option>@foreach (config('prospection.legal_bases') as $k => $l)<option value="{{ $k }}" @selected(($f['legal_basis'] ?? '') === $k)>{{ $l }}</option>@endforeach</select>
        </div>
    </div>
    <div class="form-actions">
        <a class="btn" href="{{ route('pm.segments.index') }}">Retour</a>
        <button type="submit" class="btn btn-primary"><x-icon name="check" /> Enregistrer et calculer</button>
    </div>
</form>
@endsection
