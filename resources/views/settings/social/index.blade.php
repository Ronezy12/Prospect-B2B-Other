@extends('layouts.app')
@section('title', 'Connexion Meta')

@section('content')
<div class="page-header">
    <div><h1>Connexion officielle Meta</h1><p class="subtitle">Page Facebook (Messenger) et compte Instagram professionnel lié : réception des messages entrants et réponse dans la fenêtre autorisée.</p></div>
    <div class="actions"><a class="btn btn-primary" href="{{ route('pm.settings.social.create') }}"><x-icon name="plus" /> Ajouter une connexion</a></div>
</div>
@error('account')<div class="alert alert-danger"><x-icon name="circle-alert" /><span>{{ $message }}</span></div>@enderror
<div class="card flush">
    @forelse ($accounts as $account)
        <div class="settings-row">
            <div class="grow">
                <a class="row-title" href="{{ route('pm.settings.social.edit', $account) }}">{{ $account->name }}</a>
                <div class="sub">Page {{ $account->setting('page_id') }}@if ($account->setting('instagram_id')) · Instagram {{ $account->setting('instagram_id') }}@endif · API {{ $account->setting('graph_version') ?: config('prospection.social.meta.graph_version') }}</div>
                <div class="sub">{{ $account->last_tested_at ? 'Testée le '.$account->last_tested_at->format('d/m/Y H:i').' — '.$account->last_test_result : 'Jamais testée' }} · {{ $account->last_event_at ? 'Dernier événement reçu le '.$account->last_event_at->format('d/m/Y H:i') : 'Aucun événement reçu' }}</div>
            </div>
            @unless ($account->is_active)<span class="badge">Désactivée</span>@endunless
            <form method="POST" action="{{ route('pm.settings.social.test', $account) }}">@csrf<button type="submit" class="btn btn-sm"><x-icon name="search" /> Tester</button></form>
        </div>
    @empty
        <div class="empty-state"><x-icon name="share-2" /><strong>Aucune connexion</strong>Le suivi manuel des réseaux fonctionne sans connexion. Celle-ci sert uniquement à recevoir les messages adressés à votre Page.</div>
    @endforelse
</div>
<section class="card">
    <h2>Ce que permet (et ne permet pas) l'intégration</h2>
    <ul class="small">
        <li>Permis : recevoir les messages envoyés à la Page ou au compte Instagram professionnel, et y répondre dans les {{ config('prospection.social.meta.reply_window_hours') }} h qui suivent le dernier message de la personne.</li>
        <li>Non permis : écrire en premier à un prospect, envoyer des messages en masse, ou répondre hors de la fenêtre (sauf étiquettes de message soumises à l'examen de l'application par Meta, non activées ici).</li>
        <li>LinkedIn : l'API de messagerie est réservée aux partenaires approuvés ; le suivi LinkedIn reste donc manuel.</li>
        <li>Les règles de Meta évoluent : vérifiez-les dans la documentation officielle avant la mise en production.</li>
    </ul>
</section>
@endsection
