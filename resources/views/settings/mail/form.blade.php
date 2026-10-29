@extends('layouts.app')
@section('title', $account->exists ? 'Compte d\'envoi' : 'Nouveau compte d\'envoi')

@section('content')
<div class="page-header">
    <div>
        <div class="breadcrumb"><a href="{{ route('pm.settings.mail.index') }}">Comptes d'envoi</a></div>
        <h1>{{ $account->exists ? $account->name : 'Nouveau compte d\'envoi' }}</h1>
    </div>
</div>

<form method="POST" action="{{ $account->exists ? route('pm.settings.mail.update', $account) : route('pm.settings.mail.store') }}" class="card" autocomplete="off">
    @csrf
    @if ($account->exists) @method('PUT') @endif
    <fieldset>
        <legend><x-icon name="mail" /> Expéditeur</legend>
        <div class="form-grid">
            <div class="field"><label for="name">Nom du compte *</label><input type="text" id="name" name="name" value="{{ old('name', $account->name) }}" required maxlength="120" placeholder="ex. SMTP OVH principal"></div>
            <div class="field">
                <label for="driver">Type *</label>
                <select id="driver" name="driver" required>
                    @foreach ($drivers as $key => $label)<option value="{{ $key }}" @selected(old('driver', $account->driver) === $key)>{{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="field"><label for="from_name">Nom d'expéditeur *</label><input type="text" id="from_name" name="from_name" value="{{ old('from_name', $account->from_name) }}" required maxlength="120" placeholder="Entreprise AR"></div>
            <div class="field"><label for="from_email">Adresse d'expédition *</label><input type="email" id="from_email" name="from_email" value="{{ old('from_email', $account->from_email) }}" required maxlength="190" placeholder="contact@votre-domaine.fr">@error('from_email')<div class="error">{{ $message }}</div>@enderror</div>
            <div class="field"><label for="reply_to">Adresse de réponse</label><input type="email" id="reply_to" name="reply_to" value="{{ old('reply_to', $account->reply_to) }}" maxlength="190"></div>
            <div class="field"><label class="checkbox"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $account->is_active))> Compte actif</label></div>
        </div>
    </fieldset>

    <fieldset>
        <legend><x-icon name="settings" /> Connexion SMTP <span class="muted small">si type « Serveur SMTP »</span></legend>
        <div class="form-grid">
            <div class="field"><label for="host">Serveur</label><input type="text" id="host" name="host" value="{{ old('host', $account->setting('host')) }}" maxlength="190" placeholder="ssl0.ovh.net">@error('host')<div class="error">{{ $message }}</div>@enderror</div>
            <div class="field"><label for="port">Port</label><input type="number" id="port" name="port" value="{{ old('port', $account->setting('port', 587)) }}" min="1" max="65535"></div>
            <div class="field">
                <label for="encryption">Chiffrement</label>
                <select id="encryption" name="encryption">
                    @foreach (['tls' => 'STARTTLS (port 587)', 'ssl' => 'SSL/TLS (port 465)', 'none' => 'Aucun (déconseillé)'] as $key => $label)<option value="{{ $key }}" @selected(old('encryption', $account->setting('encryption', 'tls')) === $key)>{{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="field"><label for="username">Identifiant</label><input type="text" id="username" name="username" value="{{ old('username', $account->setting('username')) }}" maxlength="190" autocomplete="off"></div>
            <div class="field">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" autocomplete="new-password" placeholder="{{ $account->hasSecret('password') ? '•••••••• (enregistré)' : '' }}">
                <div class="hint">{{ $account->hasSecret('password') ? 'Laisser vide pour conserver le mot de passe enregistré.' : 'Stocké chiffré, jamais réaffiché.' }}</div>
            </div>
        </div>
    </fieldset>

    <fieldset>
        <legend><x-icon name="sparkles" /> Fournisseur API <span class="muted small">si type « API Brevo »</span></legend>
        <div class="form-grid">
            <div class="field full">
                <label for="api_key">Clé API</label>
                <input type="password" id="api_key" name="api_key" autocomplete="new-password" placeholder="{{ $account->hasSecret('api_key') ? '•••••••• (enregistrée)' : 'À renseigner quand vous aurez ouvert un compte' }}">
                <div class="hint">Emplacement prévu pour plus tard. La clé est stockée chiffrée ; les statistiques de livraison, d'ouverture et de rebond arriveront avec les webhooks (phase 7).</div>
            </div>
        </div>
    </fieldset>

    <fieldset>
        <legend><x-icon name="clock" /> Limites d'envoi</legend>
        <div class="form-grid">
            <div class="field"><label for="hourly_limit">Par heure *</label><input type="number" id="hourly_limit" name="hourly_limit" value="{{ old('hourly_limit', $account->hourly_limit) }}" min="1" required></div>
            <div class="field"><label for="daily_limit">Par 24 heures *</label><input type="number" id="daily_limit" name="daily_limit" value="{{ old('daily_limit', $account->daily_limit) }}" min="1" required><div class="hint">Respectez les quotas de votre hébergeur : au-delà, les envois sont suspendus puis repris automatiquement.</div></div>
        </div>
    </fieldset>

    <div class="form-actions">
        <a class="btn" href="{{ route('pm.settings.mail.index') }}">Annuler</a>
        <button type="submit" class="btn btn-primary"><x-icon name="check" /> Enregistrer</button>
    </div>
</form>
@endsection
