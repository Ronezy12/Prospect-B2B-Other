@extends('layouts.app')
@section('title', $inbox->exists ? $inbox->name : 'Nouvelle boîte IMAP')

@section('content')
<div class="page-header"><div><div class="breadcrumb"><a href="{{ route('pm.settings.inboxes.index') }}">Détection des réponses</a></div><h1>{{ $inbox->exists ? $inbox->name : 'Nouvelle boîte IMAP' }}</h1></div></div>
<form method="POST" action="{{ $inbox->exists ? route('pm.settings.inboxes.update', $inbox) : route('pm.settings.inboxes.store') }}" class="card" autocomplete="off">
    @csrf
    @if ($inbox->exists) @method('PUT') @endif
    <div class="form-grid">
        <div class="field"><label for="name">Nom *</label><input type="text" id="name" name="name" value="{{ old('name', $inbox->name) }}" required maxlength="120" placeholder="ex. Boîte commerciale"></div>
        <div class="field"><label for="folder">Dossier *</label><input type="text" id="folder" name="folder" value="{{ old('folder', $inbox->folder) }}" required maxlength="120"></div>
        <div class="field"><label for="host">Serveur IMAP *</label><input type="text" id="host" name="host" value="{{ old('host', $inbox->setting('host')) }}" required maxlength="190" placeholder="ssl0.ovh.net / imap.gmail.com / outlook.office365.com"></div>
        <div class="field"><label for="port">Port *</label><input type="number" id="port" name="port" value="{{ old('port', $inbox->setting('port', 993)) }}" required min="1" max="65535"></div>
        <div class="field">
            <label for="encryption">Chiffrement *</label>
            <select id="encryption" name="encryption">@foreach (['ssl' => 'SSL/TLS (993)', 'tls' => 'STARTTLS (143)', 'none' => 'Aucun (déconseillé)'] as $k => $l)<option value="{{ $k }}" @selected(old('encryption', $inbox->setting('encryption', 'ssl')) === $k)>{{ $l }}</option>@endforeach</select>
        </div>
        <div class="field"><label for="username">Identifiant *</label><input type="text" id="username" name="username" value="{{ old('username', $inbox->setting('username')) }}" required maxlength="190"></div>
        <div class="field">
            <label for="password">Mot de passe {{ $inbox->exists ? '' : '*' }}</label>
            <input type="password" id="password" name="password" autocomplete="new-password" @required(! $inbox->exists) placeholder="{{ $inbox->exists ? '•••••••• (enregistré)' : '' }}">
            <div class="hint">Chiffré, jamais réaffiché. Pour Gmail ou Microsoft 365, utilisez un mot de passe d'application.</div>
        </div>
        <div class="field"><label class="checkbox"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $inbox->is_active))> Consulter cette boîte</label></div>
    </div>
    <div class="form-actions"><a class="btn" href="{{ route('pm.settings.inboxes.index') }}">Annuler</a><button type="submit" class="btn btn-primary"><x-icon name="check" /> Enregistrer</button></div>
</form>
@endsection
