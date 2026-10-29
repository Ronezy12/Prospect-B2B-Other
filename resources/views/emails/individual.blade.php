@extends('layouts.app')
@section('title', 'E-mail à '.$contact->fullName())

@section('content')
<div class="page-header">
    <div>
        <div class="breadcrumb"><a href="{{ route('pm.companies.show', $contact->company) }}">{{ $contact->company->name }}</a></div>
        <h1>E-mail à {{ $contact->fullName() }}</h1>
        <p class="subtitle">{{ $contact->email }}</p>
    </div>
</div>

@if ($blocked)
    <div class="alert alert-danger"><x-icon name="shield-check" /><span>Envoi impossible : {{ config('prospection.exclusion_reasons')[$blocked] ?? $blocked }}.</span></div>
@elseif ($accounts->isEmpty())
    <div class="alert alert-warning"><x-icon name="circle-alert" /><span>Aucun compte d'envoi testé avec succès. Un administrateur doit en configurer un dans Paramètres › Comptes d'envoi.</span></div>
@else
    <form method="POST" action="{{ route('pm.contacts.email.store', $contact) }}" class="card">
        @csrf
        <div class="form-grid">
            <div class="field"><label for="mail_account_id">Compte d'envoi</label><select id="mail_account_id" name="mail_account_id">@foreach ($accounts as $a)<option value="{{ $a->id }}">{{ $a->name }} ({{ $a->from_email }})</option>@endforeach</select></div>
            <div class="field"><label for="subject">Objet *</label><input type="text" id="subject" name="subject" value="{{ old('subject') }}" required maxlength="190"></div>
            <div class="field full"><label for="body">Message *</label><textarea id="body" name="body" required maxlength="20000" rows="12">{{ old('body', 'Bonjour '.($contact->first_name ?? '').",\n\n") }}</textarea>@error('body')<div class="error">{{ $message }}</div>@enderror</div>
        </div>
        <p class="small muted">Votre nom est ajouté en signature et un lien d'opposition figure en pied de message. L'envoi est tracé dans l'historique de l'entreprise.</p>
        <div class="form-actions">
            <a class="btn" href="{{ route('pm.companies.show', $contact->company) }}">Annuler</a>
            <button type="submit" class="btn btn-primary"><x-icon name="mail" /> Envoyer</button>
        </div>
    </form>
@endif
@endsection
