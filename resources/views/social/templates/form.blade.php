@extends('layouts.app')
@section('title', $template->exists ? $template->name : 'Nouveau modèle de message')

@section('content')
<div class="page-header"><div><div class="breadcrumb"><a href="{{ route('pm.social.templates.index') }}">Modèles de messages</a></div><h1>{{ $template->exists ? $template->name : 'Nouveau modèle de message' }}</h1></div></div>
<form method="POST" action="{{ $template->exists ? route('pm.social.templates.update', $template) : route('pm.social.templates.store') }}" class="card">
    @csrf
    @if ($template->exists) @method('PUT') @endif
    <div class="form-grid">
        <div class="field"><label for="name">Nom *</label><input type="text" id="name" name="name" value="{{ old('name', $template->name) }}" required maxlength="150" placeholder="ex. Invitation agences immobilières">@error('name')<div class="error">{{ $message }}</div>@enderror</div>
        <div class="field">
            <label for="kind">Usage *</label>
            <select id="kind" name="kind">@foreach (config('prospection.social.template_kinds') as $k => $l)<option value="{{ $k }}" @selected(old('kind', $template->kind) === $k)>{{ $l }}</option>@endforeach</select>
        </div>
        <div class="field">
            <label for="platform">Réseau</label>
            <select id="platform" name="platform"><option value="">Tous les réseaux</option>@foreach (config('prospection.social.platforms') as $k => $p)<option value="{{ $k }}" @selected(old('platform', $template->platform) === $k)>{{ $p['label'] }}</option>@endforeach</select>
        </div>
        <div class="field"><label class="checkbox"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $template->is_active))> Proposer ce modèle</label></div>
        <div class="field full">
            <label for="body">Texte *</label>
            <textarea id="body" name="body" required maxlength="3000" rows="8" data-count="body-count">{{ old('body', $template->body) }}</textarea>
            <div class="hint"><span id="body-count">{{ mb_strlen((string) old('body', $template->body)) }}</span> caractères — une note d'invitation LinkedIn est limitée à environ 300 caractères (limite susceptible d'évoluer). Variables : @foreach (config('prospection.social.template_variables') as $v)<code>&#123;&#123;{{ $v }}&#125;&#125;</code> @endforeach</div>
            @error('body')<div class="error">{{ $message }}</div>@enderror
        </div>
    </div>
    <div class="form-actions"><a class="btn" href="{{ route('pm.social.templates.index') }}">Annuler</a><button type="submit" class="btn btn-primary"><x-icon name="check" /> Enregistrer</button></div>
</form>
@endsection
