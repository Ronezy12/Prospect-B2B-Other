@extends('layouts.app')
@section('title', 'Base de prospects')

@section('content')
<div class="page-header">
    <div>
        <h1>Base de prospects</h1>
        <p class="subtitle">{{ number_format($companies->total(), 0, ',', ' ') }} fiche(s) correspondant à vos critères</p>
    </div>
    <div class="actions">
        @can('prospects.export')
            <a class="btn" href="{{ route('pm.companies.export', request()->query()) }}"><x-icon name="download" /> Exporter</a>
        @endcan
        @can('prospects.import')
            <a class="btn" href="{{ route('pm.imports.create') }}"><x-icon name="upload" /> Importer</a>
        @endcan
        @can('prospects.create')
            <a class="btn btn-primary" href="{{ route('pm.companies.create') }}"><x-icon name="plus" /> Nouveau prospect</a>
        @endcan
    </div>
</div>

@php($advanced = collect($filters)->except(['q', 'lifecycle'])->count())
<form class="card" method="GET" action="{{ route('pm.companies.index') }}" role="search">
    <div class="filters">
        <div class="field wide">
            <label for="q">Recherche</label>
            <div class="searchbox"><x-icon name="search" /><input type="search" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Nom, ville, e-mail, SIREN, interlocuteur…"></div>
        </div>
        <div class="field">
            <label for="lifecycle">Cycle</label>
            <select id="lifecycle" name="lifecycle">
                <option value="prospect" @selected(($filters['lifecycle'] ?? 'prospect') === 'prospect')>Prospects</option>
                <option value="client" @selected(($filters['lifecycle'] ?? null) === 'client')>Convertis en clients</option>
                <option value="all" @selected(request('lifecycle') === 'all')>Tous</option>
            </select>
        </div>
        <div class="field buttons">
            <button type="submit" class="btn btn-primary"><x-icon name="search" /> Rechercher</button>
        </div>
    </div>
    <details class="filter-panel section-gap" @if ($advanced) open @endif>
        <summary><x-icon name="filter" /> Filtres avancés @if ($advanced)<span class="filter-count">{{ $advanced }}</span>@endif</summary>
    <div class="filters">
        <div class="field">
            <label for="sector_id">Secteur</label>
            <select id="sector_id" name="sector_id">
                <option value="">Tous</option>
                @foreach ($sectors as $sector)
                    <option value="{{ $sector->id }}" @selected(($filters['sector_id'] ?? '') == $sector->id)>{{ $sector->name }}</option>
                    @foreach ($sector->children as $child)
                        <option value="{{ $child->id }}" @selected(($filters['sector_id'] ?? '') == $child->id)>— {{ $child->name }}</option>
                    @endforeach
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="status">Statut</label>
            <select id="status" name="status">
                <option value="">Tous</option>
                @foreach ($statuses as $key => $label)
                    <option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="source_id">Source</label>
            <select id="source_id" name="source_id">
                <option value="">Toutes</option>
                @foreach ($sources as $source)
                    <option value="{{ $source->id }}" @selected(($filters['source_id'] ?? '') == $source->id)>{{ $source->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="owner_id">Responsable</label>
            <select id="owner_id" name="owner_id">
                <option value="">Tous</option>
                <option value="none" @selected(($filters['owner_id'] ?? '') === 'none')>Non assigné</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}" @selected(($filters['owner_id'] ?? '') == $user->id)>{{ $user->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="city">Ville</label>
            <input type="text" id="city" name="city" value="{{ $filters['city'] ?? '' }}">
        </div>
        <div class="field">
            <label for="postal_code">Code postal / département</label>
            <input type="text" id="postal_code" name="postal_code" value="{{ $filters['postal_code'] ?? '' }}" inputmode="numeric" placeholder="ex. 75 ou 69003">
        </div>
        <div class="field">
            <label for="tag_id">Tag</label>
            <select id="tag_id" name="tag_id">
                <option value="">Tous</option>
                @foreach ($tags as $tag)
                    <option value="{{ $tag->id }}" @selected(($filters['tag_id'] ?? '') == $tag->id)>{{ $tag->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="sort">Tri</label>
            <select id="sort" name="sort">
                <option value="created_at" @selected($sort === 'created_at')>Date de création</option>
                <option value="name" @selected($sort === 'name')>Raison sociale</option>
                <option value="city" @selected($sort === 'city')>Ville</option>
                <option value="status" @selected($sort === 'status')>Statut</option>
            </select>
        </div>
        <div class="field">
            <label for="dir">Ordre</label>
            <select id="dir" name="dir">
                <option value="desc" @selected($direction === 'desc')>Décroissant</option>
                <option value="asc" @selected($direction === 'asc')>Croissant</option>
            </select>
        </div>
        <div class="field buttons">
            <button type="submit" class="btn">Appliquer</button>
            <a class="btn btn-ghost" href="{{ route('pm.companies.index') }}">Réinitialiser</a>
        </div>
    </div>
    </details>
</form>

<div class="card flush">
    <form method="POST" action="{{ route('pm.companies.bulk') }}" data-bulk-form>
        @csrf
        @can('prospects.bulk_update')
        <div class="bulk-bar">
            <span><strong data-bulk-count>0</strong> sélectionnée(s)</span>
            <label class="sr-only" for="bulk-action">Action groupée</label>
            <select id="bulk-action" name="action">
                <option value="">Action groupée…</option>
                @can('prospects.assign')<option value="assign">Assigner un responsable</option>@endcan
                <option value="status">Changer le statut</option>
                <option value="sector">Changer le secteur</option>
                <option value="add_tag">Ajouter un tag</option>
                @can('prospects.delete')<option value="delete">Supprimer</option>@endcan
            </select>
            <span data-bulk-option="assign" hidden>
                <select name="owner_id" aria-label="Responsable">
                    @foreach ($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach
                </select>
            </span>
            <span data-bulk-option="status" hidden>
                <select name="status" aria-label="Statut">
                    @foreach ($statuses as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                </select>
            </span>
            <span data-bulk-option="sector" hidden>
                <select name="sector_id" aria-label="Secteur">
                    @foreach ($sectors as $sector)<option value="{{ $sector->id }}">{{ $sector->name }}</option>@endforeach
                </select>
            </span>
            <span data-bulk-option="add_tag" hidden>
                <input type="text" name="tag" maxlength="80" aria-label="Tag" placeholder="Nom du tag">
            </span>
            <button type="submit" class="btn btn-sm btn-primary" data-bulk-submit disabled>Appliquer à la sélection</button>
        </div>
        @endcan

        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    @can('prospects.bulk_update')
                        <th class="col-check"><input type="checkbox" data-check-all aria-label="Tout sélectionner"></th>
                    @endcan
                    <th>Entreprise</th>
                    <th>Secteur</th>
                    <th>Localisation</th>
                    <th>Statut</th>
                    <th>Responsable</th>
                    <th>Contacts</th>
                    <th>Créé le</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($companies as $company)
                    <tr>
                        @can('prospects.bulk_update')
                            <td><input type="checkbox" name="ids[]" value="{{ $company->id }}" data-bulk-item aria-label="Sélectionner {{ $company->name }}"></td>
                        @endcan
                        <td>
                            <a class="row-title" href="{{ route('pm.companies.show', $company) }}">{{ $company->name }}</a>
                            @if ($company->isClient())<span class="badge badge-success">Client</span>@endif
                            @if ($company->tags->isNotEmpty())<div>@foreach ($company->tags as $tag)<span class="tag">{{ $tag->name }}</span>@endforeach</div>@endif
                        </td>
                        <td>{{ $company->sector?->name ?? '—' }}</td>
                        <td>{{ trim($company->postal_code.' '.$company->city) ?: '—' }}</td>
                        <td>@include('partials.status-badge', ['status' => $company->status])</td>
                        <td>@if ($company->owner)<span class="person"><x-avatar :user="$company->owner" size="sm" /> {{ $company->owner->name }}</span>@else<span class="muted">Non assigné</span>@endif</td>
                        <td class="num">{{ $company->contacts_count }}</td>
                        <td class="nowrap muted">{{ $company->created_at->format('d/m/Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="table-empty"><div class="empty-state"><x-icon name="building-2" /><strong>Aucun prospect trouvé</strong>Modifiez vos filtres ou créez un nouveau prospect.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </form>
    {{ $companies->links('partials.pagination') }}
</div>
@endsection
