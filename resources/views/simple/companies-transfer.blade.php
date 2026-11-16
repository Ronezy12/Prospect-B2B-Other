@extends('layouts.app')
@section('title', 'Importer ou exporter des prospects')

@section('content')
<a class="s-back" data-back href="{{ route('pm.companies.index') }}"><x-icon name="chevron-right" class="flip" /> Retour</a>
<h1 class="s-h1">Importer ou exporter des prospects</h1>
<p class="s-lead">Ajoutez beaucoup de prospects d'un coup à partir d'un fichier Excel, ou récupérez toute votre liste.</p>

<div class="tr-grid">
    @can('prospects.import')
    <section class="s-card">
        <h2><x-icon name="upload" /> Importer un fichier</h2>
        <ol class="tr-steps">
            <li>Téléchargez le fichier modèle et ouvrez-le dans Excel. <a class="btn btn-sm" href="{{ route('pm.companies.template') }}"><x-icon name="download" /> Fichier modèle</a></li>
            <li>Une ligne par prospect. Gardez la première ligne (les titres des colonnes).</li>
            <li>Enregistrez-le, puis déposez-le ci-dessous.</li>
        </ol>
        <form method="POST" action="{{ route('pm.companies.import') }}" enctype="multipart/form-data">
            @csrf
            <label class="tr-drop" data-drop>
                <x-icon name="file-spreadsheet" />
                <span>Glissez votre fichier ici ou cliquez pour le choisir</span>
                <span class="tr-file" data-drop-name>Aucun fichier choisi</span>
                <input type="file" name="file" accept=".csv,.xlsx,.xls" required>
            </label>
            @error('file')<div class="error">{{ $message }}</div>@enderror
            <div class="field">
                <label for="duplicates">Si une entreprise existe déjà</label>
                <select id="duplicates" name="duplicates">
                    <option value="fill_empty" @selected(old('duplicates', 'fill_empty') === 'fill_empty')>Compléter sa fiche avec les informations manquantes</option>
                    <option value="skip" @selected(old('duplicates') === 'skip')>Ne pas y toucher</option>
                </select>
            </div>
            <label class="checkbox"><input type="checkbox" name="confirm" value="1" @checked(old('confirm'))> Ces contacts sont des professionnels et je sais d'où viennent ces informations.</label>
            @error('confirm')<div class="error">{{ $message }}</div>@enderror
            <p class="s-hint">Les adresses de la liste d'opposition sont écartées automatiquement. Formats : Excel (.xlsx, .xls) ou CSV.</p>
            <button type="submit" class="btn btn-primary s-submit"><x-icon name="upload" /> Importer</button>
        </form>

        @if ($imports->isNotEmpty())
            <h3 class="s-h3">Mes derniers imports</h3>
            <ul class="tr-list">
                @foreach ($imports as $import)
                    <li>
                        <span><strong>{{ $import->original_name }}</strong> — {{ $import->created_at->format('d/m à H:i') }}</span>
                        <span class="tr-counts">
                            {{ $import->statusLabel() }}
                            @if ($import->status === 'completed') · {{ $import->created_count }} ajouté(s), {{ $import->updated_count }} complété(s), {{ $import->skipped_count }} écarté(s), {{ $import->error_count }} erreur(s)@endif
                            · <a href="{{ route('pm.imports.show', $import) }}">Détail</a>
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
    @endcan

    @can('export', App\Models\Company::class)
    <section class="s-card">
        <h2><x-icon name="download" /> Exporter mes prospects</h2>
        <p class="s-sub">Un fichier qui s'ouvre dans Excel, avec l'adresse, le code postal, la ville, la personne à contacter et le statut. {{ $total }} prospect(s) au total.</p>
        <form method="GET" action="{{ route('pm.companies.export') }}">
            <div class="field">
                <label for="status">Quels prospects ?</label>
                <select id="status" name="status">
                    <option value="">Tous</option>
                    @foreach (config('prospection.company_statuses') as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field"><label for="city">Ville (facultatif)</label><input type="text" id="city" name="city" maxlength="120" placeholder="Toutes les villes"></div>
            <button type="submit" class="btn btn-primary s-submit"><x-icon name="download" /> Télécharger le fichier</button>
        </form>
    </section>
    @endcan
</div>
@endsection
