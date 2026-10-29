@extends('layouts.app')
@section('title', 'Nouveau modèle')

@section('content')
<div class="page-header">
    <div>
        <div class="breadcrumb"><a href="{{ route('pm.templates.index') }}">Modèles d'e-mails</a></div>
        <h1>Nouveau modèle</h1>
    </div>
</div>
<form method="POST" action="{{ route('pm.templates.store') }}" class="card">
    @csrf
    <div class="form-grid">
        <div class="field"><label for="name">Nom du modèle *</label><input type="text" id="name" name="name" value="{{ old('name') }}" required maxlength="150" placeholder="ex. Prospection agences immobilières"></div>
        <div class="field">
            <label for="category">Catégorie *</label>
            <select id="category" name="category" required>
                @foreach (\App\Http\Controllers\TemplateController::CATEGORIES as $key => $label)<option value="{{ $key }}" @selected(old('category') === $key)>{{ $label }}</option>@endforeach
            </select>
            <div class="hint">Une campagne ne peut utiliser qu'un modèle de sa catégorie.</div>
        </div>
        <div class="field full">
            <label>Point de départ</label>
            <div class="choice-list">
                @foreach ($starters as $key => $label)
                    <label class="checkbox"><input type="radio" name="starter" value="{{ $key }}" @checked(old('starter', 'prospection') === $key)> {{ $label }}</label>
                @endforeach
            </div>
        </div>
    </div>
    <div class="form-actions">
        <a class="btn" href="{{ route('pm.templates.index') }}">Annuler</a>
        <button type="submit" class="btn btn-primary"><x-icon name="sparkles" /> Créer et ouvrir l'éditeur</button>
    </div>
</form>
@endsection
