@extends('layouts.app')
@section('title', $account->exists ? $account->name : 'Nouvelle connexion Meta')

@section('content')
<div class="page-header"><div><div class="breadcrumb"><a href="{{ route('pm.settings.social.index') }}">Connexion Meta</a></div><h1>{{ $account->exists ? $account->name : 'Nouvelle connexion Meta' }}</h1></div></div>
@error('account')<div class="alert alert-danger"><x-icon name="circle-alert" /><span>{{ $message }}</span></div>@enderror

@if ($account->exists)
    <section class="card">
        <h2>Webhook à déclarer dans votre application Meta</h2>
        <dl class="details">
            <dt>URL de rappel</dt><dd><code class="break">{{ route('webhooks.meta', $account->webhook_token) }}</code></dd>
            <dt>Jeton de vérification</dt><dd>Celui saisi ci-dessous (non réaffiché).</dd>
            <dt>Champs à souscrire</dt><dd><code>messages</code>, <code>messaging_postbacks</code> (Page) et <code>messages</code> (Instagram)</dd>
        </dl>
        <p class="small muted">Chaque événement est authentifié par la signature <code>X-Hub-Signature-256</code> calculée avec le secret de l'application. L'URL contient un jeton secret : ne la diffusez pas.</p>
        <form method="POST" action="{{ route('pm.settings.social.test', $account) }}">@csrf<button type="submit" class="btn"><x-icon name="search" /> Tester la connexion à la Page</button></form>
    </section>
@endif

<form method="POST" action="{{ $account->exists ? route('pm.settings.social.update', $account) : route('pm.settings.social.store') }}" class="card" autocomplete="off">
    @csrf
    @if ($account->exists) @method('PUT') @endif
    <div class="form-grid">
        <div class="field"><label for="name">Nom *</label><input type="text" id="name" name="name" value="{{ old('name', $account->name) }}" required maxlength="120" placeholder="ex. Page Entreprise AR">@error('name')<div class="error">{{ $message }}</div>@enderror</div>
        <div class="field"><label for="graph_version">Version de l'API Graph</label><input type="text" id="graph_version" name="graph_version" value="{{ old('graph_version', $account->setting('graph_version')) }}" maxlength="8" placeholder="{{ config('prospection.social.meta.graph_version') }}">@error('graph_version')<div class="error">{{ $message }}</div>@enderror</div>
        <div class="field"><label for="app_id">Identifiant de l'application *</label><input type="text" id="app_id" name="app_id" value="{{ old('app_id', $account->setting('app_id')) }}" required inputmode="numeric" maxlength="40">@error('app_id')<div class="error">{{ $message }}</div>@enderror</div>
        <div class="field">
            <label for="app_secret">Secret de l'application {{ $account->exists ? '' : '*' }}</label>
            <input type="password" id="app_secret" name="app_secret" autocomplete="new-password" @required(! $account->exists) placeholder="{{ $account->exists ? '•••••••• (enregistré)' : '' }}">
            @error('app_secret')<div class="error">{{ $message }}</div>@enderror
        </div>
        <div class="field"><label for="page_id">Identifiant de la Page *</label><input type="text" id="page_id" name="page_id" value="{{ old('page_id', $account->setting('page_id')) }}" required inputmode="numeric" maxlength="40">@error('page_id')<div class="error">{{ $message }}</div>@enderror</div>
        <div class="field"><label for="instagram_id">Identifiant du compte Instagram professionnel</label><input type="text" id="instagram_id" name="instagram_id" value="{{ old('instagram_id', $account->setting('instagram_id')) }}" inputmode="numeric" maxlength="40"><div class="hint">Facultatif : uniquement si un compte Instagram est lié à la Page.</div>@error('instagram_id')<div class="error">{{ $message }}</div>@enderror</div>
        <div class="field full">
            <label for="page_access_token">Jeton d'accès de la Page {{ $account->exists ? '' : '*' }}</label>
            <input type="password" id="page_access_token" name="page_access_token" autocomplete="new-password" @required(! $account->exists) placeholder="{{ $account->exists ? '•••••••• (enregistré)' : '' }}">
            <div class="hint">Jeton longue durée avec les autorisations <code>pages_messaging</code> (et <code>instagram_manage_messages</code> pour Instagram). Chiffré en base, jamais réaffiché ni journalisé.</div>
            @error('page_access_token')<div class="error">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label for="verify_token">Jeton de vérification du webhook {{ $account->exists ? '' : '*' }}</label>
            <input type="password" id="verify_token" name="verify_token" autocomplete="new-password" @required(! $account->exists) minlength="16" maxlength="128" placeholder="{{ $account->exists ? '•••••••• (enregistré)' : '16 caractères minimum' }}">
            <div class="hint">Chaîne de votre choix, à saisir aussi dans la configuration du webhook chez Meta.</div>
            @error('verify_token')<div class="error">{{ $message }}</div>@enderror
        </div>
        <div class="field"><label class="checkbox"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $account->is_active))> Connexion active</label></div>
    </div>
    <div class="form-actions"><a class="btn" href="{{ route('pm.settings.social.index') }}">Annuler</a><button type="submit" class="btn btn-primary"><x-icon name="check" /> Enregistrer</button></div>
</form>
@endsection
