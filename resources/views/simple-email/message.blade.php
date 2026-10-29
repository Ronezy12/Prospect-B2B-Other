@extends('layouts.app')
@section('title', 'Écrire à plusieurs entreprises')

@section('content')
<div class="page-header">
    <div><h1>Écrire à plusieurs entreprises</h1><p class="subtitle">Écrivez comme un e-mail normal : le prénom et l'entreprise seront remplis pour chacun.</p></div>
</div>
@include('simple-email.steps', ['current' => 2])

<div class="example-picker">
    <span class="small muted">Partir d'un exemple :</span>
    @foreach ($examples as $key => $ex)<a class="btn btn-sm" href="{{ route('pm.simple-email.message', ['exemple' => $key]) }}">{{ $ex['label'] }}</a>@endforeach
</div>

<form method="POST" action="{{ route('pm.simple-email.message.store') }}" class="card wizard-card">
    @csrf
    <div class="field">
        <label for="subject">Objet</label>
        <input type="text" id="subject" name="subject" value="{{ old('subject', $subject) }}" required maxlength="190">
        @error('subject')<div class="error">{{ $message }}</div>@enderror
    </div>
    <div class="field">
        <label for="body">Message</label>
        <div class="insert-bar">
            <span class="small muted">Insérer :</span>
            <button type="button" class="btn btn-sm" data-insert="@{{prenom}}" data-into="body">Prénom</button>
            <button type="button" class="btn btn-sm" data-insert="@{{entreprise}}" data-into="body">Entreprise</button>
            <button type="button" class="btn btn-sm" data-insert="@{{ville}}" data-into="body">Ville</button>
        </div>
        <textarea id="body" name="body" required maxlength="10000" rows="12">{{ old('body', $body) }}</textarea>
        <div class="hint">Votre nom est ajouté en signature. Un lien « Se désinscrire » est ajouté automatiquement en bas de l'e-mail.</div>
        @error('body')<div class="error">{{ $message }}</div>@enderror
    </div>
    @if ($accounts->count() > 1)
        <div class="field">
            <label for="mail_account_id">Envoyer depuis</label>
            <select id="mail_account_id" name="mail_account_id">@foreach ($accounts as $a)<option value="{{ $a->id }}" @selected((int) old('mail_account_id', $accountId) === $a->id)>{{ $a->name }} ({{ $a->from_email }})</option>@endforeach</select>
        </div>
    @elseif ($accounts->count() === 1)
        <input type="hidden" name="mail_account_id" value="{{ $accounts->first()->id }}">
        <p class="small muted">Envoyé depuis {{ $accounts->first()->from_email }}@if ($accounts->first()->driver === 'log') (mode démonstration : rien ne part réellement)@endif.</p>
    @else
        <div class="alert alert-warning"><x-icon name="circle-alert" /><span>Aucune adresse d'envoi n'est prête. Un administrateur doit la configurer dans Paramètres › Envoi d'e-mails.</span></div>
    @endif
    @error('mail_account_id')<div class="error">{{ $message }}</div>@enderror
    <div class="form-actions">
        <a class="btn" href="{{ route('pm.simple-email.create') }}"><x-icon name="chevron-right" class="flip" /> Retour</a>
        <button type="submit" class="btn btn-primary">Continuer <x-icon name="arrow-right" /></button>
    </div>
</form>
@endsection
