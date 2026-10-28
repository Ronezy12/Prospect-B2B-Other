@extends('layouts.app')
@section('title', 'Nouvel import')

@section('content')
<div class="page-header">
    <div>
        <div class="breadcrumb"><a href="{{ route('pm.imports.index') }}">Imports</a></div>
        <h1>Nouvel import</h1>
    </div>
</div>
<div class="steps"><span class="step current"><b>1</b> Fichier</span><span class="step"><b>2</b> Correspondance</span><span class="step"><b>3</b> Traitement</span></div>

<div class="grid grid-sidebar">
    <form method="POST" action="{{ route('pm.imports.store') }}" enctype="multipart/form-data" class="card">
        @csrf
        <div class="field">
            <label for="file">Fichier CSV ou Excel</label>
            <input type="file" id="file" name="file" required accept=".csv,.txt,.xlsx,.xls">
            <div class="hint">Taille maximale : {{ round(config('prospection.import.max_file_kb') / 1024) }} Mo. La première ligne doit contenir les en-têtes. Pour Excel, seule la première feuille est lue.</div>
            @error('file')<div class="error">{{ $message }}</div>@enderror
        </div>
        <div class="form-actions">
            <a class="btn" href="{{ route('pm.imports.index') }}">Annuler</a>
            <button type="submit" class="btn btn-primary"><x-icon name="sparkles" /> Analyser le fichier</button>
        </div>
    </form>

    <aside class="card">
        <h2>Colonnes reconnues</h2>
        <p class="small muted">Les en-têtes sont associés automatiquement lorsqu'ils correspondent ; vous pourrez ajuster la correspondance à l'étape suivante.</p>
        <ul class="small">
            @foreach ($fields as $key => $field)
                <li>{{ $field['label'] }}</li>
            @endforeach
        </ul>
    </aside>
</div>
@endsection
