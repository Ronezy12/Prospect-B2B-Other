@extends('layouts.app')
@section('title', 'Demandes des personnes')

@section('content')
<div class="page-header">
    <div>
        <h1>Demandes des personnes</h1>
        <p class="subtitle">Quelqu'un demande ce que vous savez sur lui, ou d'être effacé ? Tout se fait ici. Délai légal : un mois.</p>
    </div>
</div>
@if ($lateCount)<div class="alert alert-danger"><x-icon name="circle-alert" /><span>{{ $lateCount }} demande(s) en retard sur le délai d'un mois.</span></div>@endif

<section class="card">
    <h2><x-icon name="search" /> Que savons-nous sur cette personne ?</h2>
    <form method="GET" class="toolbar">
        <label class="sr-only" for="email">Adresse e-mail</label>
        <input type="email" id="email" name="email" value="{{ $email }}" placeholder="adresse@exemple.fr" required>
        <button type="submit" class="btn btn-primary"><x-icon name="search" /> Rechercher</button>
    </form>

    @if ($report)
        @php($n = count($report['contacts']) + count($report['emails_recus']) + count($report['abonnements']) + count($report['reseaux_sociaux']))
        @if ($n === 0 && ! $report['opposition'])
            <p class="muted">Aucune donnée trouvée pour {{ $email }}.</p>
        @else
            <div class="privacy-summary">
                <div><strong>{{ count($report['contacts']) }}</strong><span>fiche(s) contact</span></div>
                <div><strong>{{ count($report['emails_recus']) }}</strong><span>e-mail(s) envoyé(s)</span></div>
                <div><strong>{{ count($report['abonnements']) }}</strong><span>abonnement(s)</span></div>
                <div><strong>{{ count($report['reseaux_sociaux']) }}</strong><span>profil(s) social(aux)</span></div>
                <div><strong>{{ count($report['historique']) }}</strong><span>mention(s) dans l'historique</span></div>
                <div><strong>{{ $report['opposition'] ? 'Oui' : 'Non' }}</strong><span>opposition enregistrée</span></div>
            </div>
            @foreach ($report['contacts'] as $c)
                <dl class="details small">
                    <dt>Contact</dt><dd>{{ trim(($c['prenom'] ?? '').' '.($c['nom'] ?? '')) ?: '—' }} @if ($c['entreprise']) · {{ $c['entreprise'] }}@endif @if ($c['supprime_le'])<span class="badge">supprimé</span>@endif</dd>
                    <dt>Base légale / source</dt><dd>{{ $c['base_legale'] }} · {{ $c['source'] ?? 'source non renseignée' }} · collecté le {{ $c['date_de_collecte'] ?? '—' }}</dd>
                </dl>
            @endforeach
            <div class="form-actions">
                <form method="POST" action="{{ route('pm.compliance.export') }}">@csrf<input type="hidden" name="email" value="{{ $email }}"><button type="submit" class="btn"><x-icon name="download" /> Télécharger ses données (accès / portabilité)</button></form>
            </div>
            <details class="inline-form danger-zone">
                <summary>Effacer toutes les données de cette personne</summary>
                <p class="small">Les fiches contact sont supprimées, son adresse est retirée des e-mails envoyés, de l'historique et des abonnements. Seule une empreinte de l'adresse est gardée dans la liste d'opposition, pour qu'elle ne soit jamais réimportée. <strong>Irréversible.</strong></p>
                <form method="POST" action="{{ route('pm.compliance.erase') }}" data-confirm="Effacer définitivement les données de cette personne ?">
                    @csrf
                    <input type="hidden" name="email" value="{{ $email }}">
                    <div class="field"><label for="confirm_email">Retapez l'adresse pour confirmer</label><input type="email" id="confirm_email" name="confirm_email" required autocomplete="off"></div>
                    <div class="field"><label for="erase-note">Référence de la demande</label><input type="text" id="erase-note" name="note" maxlength="255" placeholder="ex. e-mail du 02/11, demande n° 12"></div>
                    <button type="submit" class="btn btn-danger"><x-icon name="trash-2" /> Effacer définitivement</button>
                </form>
            </details>
        @endif
    @endif
</section>

<div class="grid grid-sidebar section-gap">
    <section class="card flush">
        <div class="card-head"><h2>Registre des demandes</h2></div>
        @forelse ($requests as $r)
            <div class="task-row">
                <div class="grow">
                    <div class="t-title">{{ $r->typeLabel() }} @if ($r->requester_label)— {{ $r->requester_label }}@endif</div>
                    <div class="t-meta">
                        <span>Reçue le {{ $r->received_at->format('d/m/Y') }}</span>
                        <span @class(['late' => $r->isLate()])>Réponse avant le {{ $r->due_at->format('d/m/Y') }}</span>
                        <span @class(['badge', 'badge-success' => $r->status === 'traitee', 'badge-warning' => $r->status === 'ouverte'])>{{ \App\Models\PrivacyRequest::STATUSES[$r->status] }}</span>
                        @if ($r->handler)<span>par {{ $r->handler->name }}</span>@endif
                    </div>
                    @if ($r->outcome)<div class="small pre">{{ $r->outcome }}</div>@endif
                </div>
                @if ($r->status === 'ouverte')
                    <details class="outcome-menu">
                        <summary class="btn btn-sm">Clôturer</summary>
                        <div class="outcome-pop">
                            <form method="POST" action="{{ route('pm.compliance.requests.update', $r) }}">
                                @csrf @method('PUT')
                                <div class="field"><label for="st-{{ $r->id }}">Décision</label><select id="st-{{ $r->id }}" name="status"><option value="traitee">Traitée</option><option value="refusee">Refusée (motivée)</option></select></div>
                                <div class="field"><label for="oc-{{ $r->id }}">Ce qui a été fait / motif</label><textarea id="oc-{{ $r->id }}" name="outcome" required maxlength="2000"></textarea></div>
                                <button type="submit" class="btn btn-sm btn-primary">Enregistrer</button>
                            </form>
                        </div>
                    </details>
                @endif
            </div>
        @empty
            <div class="empty-state"><x-icon name="shield-check" /><strong>Aucune demande</strong>Enregistrez ici chaque demande reçue pour prouver le respect des délais.</div>
        @endforelse
        {{ $requests->links('partials.pagination') }}
    </section>

    <form method="POST" action="{{ route('pm.compliance.requests.store') }}" class="card">
        @csrf
        <h2>Nouvelle demande</h2>
        <div class="field"><label for="type">Type *</label><select id="type" name="type">@foreach (\App\Models\PrivacyRequest::TYPES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
        <div class="field"><label for="r-email">Adresse e-mail concernée</label><input type="email" id="r-email" name="email" maxlength="190"><div class="hint">Conservée uniquement sous forme d'empreinte.</div></div>
        <div class="field"><label for="requester_label">Repère</label><input type="text" id="requester_label" name="requester_label" maxlength="120" placeholder="ex. C. M., agence de Lyon"></div>
        <div class="field"><label for="channel">Reçue par</label><select id="channel" name="channel"><option value="email">E-mail</option><option value="courrier">Courrier</option><option value="telephone">Téléphone</option><option value="formulaire">Formulaire</option><option value="autre">Autre</option></select></div>
        <div class="field"><label for="received_at">Reçue le *</label><input type="date" id="received_at" name="received_at" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" required></div>
        <div class="field"><label for="r-note">Précisions</label><textarea id="r-note" name="note" maxlength="2000"></textarea></div>
        <div class="form-actions"><button type="submit" class="btn btn-primary"><x-icon name="plus" /> Enregistrer</button></div>
    </form>
</div>
@endsection
