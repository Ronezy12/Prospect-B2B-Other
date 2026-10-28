@extends('layouts.app')
@section('title', 'Correspondance des colonnes')

@section('content')
<div class="page-header">
    <div>
        <div class="breadcrumb"><a href="{{ route('pm.imports.index') }}">Imports</a> › {{ $import->original_name }}</div>
        <h1>Correspondance des colonnes</h1>
    </div>
</div>
<div class="steps"><span class="step done"><b><x-icon name="check" /></b> Fichier</span><span class="step current"><b>2</b> Correspondance</span><span class="step"><b>3</b> Traitement</span></div>

<div class="alert alert-info"><x-icon name="file-spreadsheet" /> <span>{{ number_format($import->total_rows, 0, ',', ' ') }} ligne(s) de données détectée(s) dans « {{ $import->original_name }} ».</span></div>
@if ($sameFile)
    <div class="alert alert-warning">Ce fichier identique a déjà été importé le {{ $sameFile->created_at->format('d/m/Y à H:i') }} (<a href="{{ route('pm.imports.show', $sameFile) }}">voir l'import</a>). Les doublons seront traités selon la stratégie choisie ci-dessous.</div>
@endif

<section class="card flush">
    <h2 class="card-title">Aperçu des {{ count($preview) }} premières lignes</h2>
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr><th>Ligne</th>@foreach ($import->headers as $header)<th>{{ $header !== '' ? $header : '(sans titre)' }}</th>@endforeach</tr>
            </thead>
            <tbody>
            @foreach ($preview as $number => $row)
                <tr><td>{{ $number }}</td>@foreach ($import->headers as $i => $_)<td>{{ \Illuminate\Support\Str::limit($row[$i] ?? '', 60) }}</td>@endforeach</tr>
            @endforeach
            </tbody>
        </table>
    </div>
</section>

<form method="POST" action="{{ route('pm.imports.start', $import) }}" class="card">
    @csrf
    <fieldset>
        <legend><x-icon name="arrow-right-left" /> Correspondance des colonnes</legend>
        @error('mapping')<div class="alert alert-danger">{{ $message }}</div>@enderror
        <div class="form-grid">
            @foreach ($fields as $key => $field)
                <div class="field">
                    <label for="map-{{ $key }}">{{ $field['label'] }}@if ($key === 'company_name') *@endif</label>
                    <select id="map-{{ $key }}" name="mapping[{{ $key }}]">
                        <option value="">— Ne pas importer —</option>
                        @foreach ($import->headers as $index => $header)
                            <option value="{{ $index }}" @selected((string) old('mapping.'.$key, $import->mapping[$key] ?? '') === (string) $index)>{{ $header !== '' ? $header : 'Colonne '.($index + 1) }}</option>
                        @endforeach
                    </select>
                </div>
            @endforeach
        </div>
    </fieldset>

    <fieldset>
        <legend><x-icon name="layers" /> Doublons</legend>
        <p class="small muted">Détection par SIREN, puis domaine du site web, puis raison sociale + code postal ; contacts par adresse e-mail. Les entreprises déjà clientes ne sont jamais modifiées.</p>
        <div class="choice-list">
        @foreach ($strategies as $key => $label)
            <label class="checkbox"><input type="radio" name="duplicate_strategy" value="{{ $key }}" @checked(old('duplicate_strategy', 'skip') === $key)> {{ $label }}</label>
        @endforeach
        </div>
    </fieldset>

    <fieldset>
        <legend><x-icon name="shield-check" /> Valeurs par défaut et conformité</legend>
        <div class="form-grid">
            <div class="field">
                <label for="default_sector_id">Secteur par défaut</label>
                <select id="default_sector_id" name="default_sector_id">
                    <option value="">—</option>
                    @foreach ($sectors as $sector)<option value="{{ $sector->id }}" @selected(old('default_sector_id') == $sector->id)>{{ $sector->name }}</option>@endforeach
                </select>
            </div>
            <div class="field">
                <label for="default_source_id">Source par défaut</label>
                <select id="default_source_id" name="default_source_id">
                    <option value="">—</option>
                    @foreach ($sources as $source)<option value="{{ $source->id }}" @selected(old('default_source_id') == $source->id)>{{ $source->name }}</option>@endforeach
                </select>
            </div>
            @if ($canAssign)
                <div class="field">
                    <label for="default_owner_id">Responsable par défaut</label>
                    <select id="default_owner_id" name="default_owner_id">
                        <option value="">Moi-même</option>
                        @foreach ($users as $user)<option value="{{ $user->id }}" @selected(old('default_owner_id') == $user->id)>{{ $user->name }}</option>@endforeach
                    </select>
                </div>
            @endif
            <div class="field">
                <label for="tag_ids">Tags à ajouter</label>
                <select id="tag_ids" name="tag_ids[]" multiple size="4">
                    @foreach ($tags as $tag)<option value="{{ $tag->id }}" @selected(in_array($tag->id, (array) old('tag_ids')))>{{ $tag->name }}</option>@endforeach
                </select>
            </div>
            <div class="field">
                <label for="default_audience_type">Type des contacts *</label>
                <select id="default_audience_type" name="default_audience_type" required>
                    @foreach (config('prospection.audience_types') as $key => $label)<option value="{{ $key }}" @selected(old('default_audience_type', 'b2b') === $key)>{{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="field">
                <label for="default_legal_basis">Base légale *</label>
                <select id="default_legal_basis" name="default_legal_basis" required>
                    @foreach (config('prospection.legal_bases') as $key => $label)<option value="{{ $key }}" @selected(old('default_legal_basis', 'interet_legitime') === $key)>{{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="field">
                <label for="default_collected_at">Date de collecte</label>
                <input type="date" id="default_collected_at" name="default_collected_at" value="{{ old('default_collected_at', now()->format('Y-m-d')) }}">
            </div>
            <div class="field">
                <label for="collection_note">Origine des données</label>
                <input type="text" id="collection_note" name="collection_note" value="{{ old('collection_note') }}" maxlength="255" placeholder="ex. Salon de l'immobilier 2026, annuaire X…">
            </div>
            <div class="field full">
                <label class="checkbox"><input type="checkbox" name="confirm_information" value="1" required>
                    Je confirme que la source de ces données est licite et documentée, et que les personnes seront informées du traitement (au plus tard lors du premier contact).</label>
                <div class="hint">Les adresses présentes dans la liste d'opposition ne seront pas importées.</div>
            </div>
        </div>
    </fieldset>

    <div class="form-actions">
        <a class="btn" href="{{ route('pm.imports.index') }}">Annuler</a>
        <button type="submit" class="btn btn-primary"><x-icon name="upload" /> Lancer l'import</button>
    </div>
</form>
@endsection
