@extends('layouts.app')
@section('title', 'Détection des réponses')

@section('content')
<div class="page-header">
    <div><h1>Détection des réponses (IMAP)</h1><p class="subtitle">Boîtes consultées toutes les 5 minutes. Seuls les en-têtes sont lus : le contenu des réponses n'est ni téléchargé ni stocké.</p></div>
    <div class="actions"><a class="btn btn-primary" href="{{ route('pm.settings.inboxes.create') }}"><x-icon name="plus" /> Ajouter une boîte</a></div>
</div>
@error('inbox')<div class="alert alert-danger"><x-icon name="circle-alert" /><span>{{ $message }}</span></div>@enderror
<div class="card flush">
    @forelse ($inboxes as $inbox)
        <div class="settings-row">
            <div class="grow">
                <a class="row-title" href="{{ route('pm.settings.inboxes.edit', $inbox) }}">{{ $inbox->name }}</a>
                <div class="sub">{{ $inbox->setting('username') }} · {{ $inbox->setting('host') }} · dossier {{ $inbox->folder }}</div>
                <div class="sub">{{ $inbox->last_checked_at ? 'Dernière vérification le '.$inbox->last_checked_at->format('d/m/Y H:i').' — '.$inbox->last_result : 'Jamais vérifiée' }}</div>
            </div>
            @unless ($inbox->is_active)<span class="badge">Désactivée</span>@endunless
            <form method="POST" action="{{ route('pm.settings.inboxes.check', $inbox) }}">@csrf<button type="submit" class="btn btn-sm"><x-icon name="search" /> Vérifier maintenant</button></form>
        </div>
    @empty
        <div class="empty-state"><x-icon name="mail" /><strong>Aucune boîte configurée</strong>Les réponses aux e-mails ne sont pas détectées : la statistique « réponses » reste indisponible.</div>
    @endforelse
</div>
<section class="card">
    <h2>Comment les réponses sont reconnues</h2>
    <ul class="small">
        <li>Fiable : la réponse cite l'identifiant du message envoyé (en-têtes <code>In-Reply-To</code> / <code>References</code>).</li>
        <li>Probable : à défaut, l'expéditeur a reçu un e-mail de nos campagnes dans les 60 jours précédents.</li>
        <li>Utilisez l'adresse de réponse (« reply-to ») des comptes d'envoi : c'est elle qu'il faut consulter ici.</li>
    </ul>
</section>
@endsection
