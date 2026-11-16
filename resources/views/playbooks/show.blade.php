@extends('layouts.app')
@section('title', $playbook['name'])

@section('content')
<a class="s-back" data-back href="{{ route('pm.playbooks.index') }}"><x-icon name="chevron-right" class="flip" /> Retour</a>
<div class="s-head">
    <div class="pb-title">
        <span class="pb-icon"><x-icon :name="$playbook['icon']" /></span>
        <div>
            <h1>{{ $playbook['name'] }}</h1>
            <p class="s-lead">{{ $playbook['pitch'] }}</p>
        </div>
    </div>
    @if ($sector)
        <a class="btn" href="{{ route('pm.companies.index', ['q' => null, 'sector_id' => $sector->id]) }}"><x-icon name="building-2" /> Mes prospects de ce secteur ({{ $prospectCount }})</a>
    @endif
</div>

<nav class="pb-toc" aria-label="Sommaire">
    <a href="#comprendre">Comprendre</a><a href="#qualifier">Qualifier</a><a href="#objections">Objections</a><a href="#emails">Les 10 e-mails</a>
</nav>

<h2 class="pb-h2" id="comprendre">Comprendre le secteur</h2>
<p class="s-sub"><strong>Qui contacter :</strong> {{ $playbook['decider'] }}</p>
<div class="pb-quad">
    <section class="s-card"><h3><x-icon name="circle-alert" /> Leur besoin</h3><ul>@foreach ($playbook['need'] as $l)<li>{{ $l }}</li>@endforeach</ul></section>
    <section class="s-card"><h3><x-icon name="plug-zap" /> Ce qu'on propose</h3><ul>@foreach ($playbook['offer'] as $l)<li>{{ $l }}</li>@endforeach</ul></section>
    <section class="s-card"><h3><x-icon name="trending-up" /> Ce qu'ils y gagnent</h3><ul>@foreach ($playbook['gain'] as $l)<li>{{ $l }}</li>@endforeach</ul></section>
    <section class="s-card pb-deal"><h3><x-icon name="handshake" /> L'accord équilibré</h3><ul>@foreach ($playbook['deal'] as $l)<li>{{ $l }}</li>@endforeach</ul></section>
</div>

<h2 class="pb-h2" id="qualifier">Qualifier en 6 questions</h2>
<section class="s-card">
    <p class="s-sub">À poser au premier vrai échange. Chaque réponse favorable vaut 1 point : <strong>5 ou 6</strong> → statut « Qualifié », proposez un rendez-vous ; <strong>3 ou 4</strong> → à relancer dans un mois ; <strong>0 à 2</strong> → « Non pertinent » ou « En sommeil ».</p>
    <ol class="pb-questions">@foreach ($playbook['questions'] as $q)<li>{{ $q }}</li>@endforeach</ol>
</section>

<h2 class="pb-h2" id="objections">Ce qu'ils vont vous répondre</h2>
<div class="pb-objections">
    @foreach ($playbook['objections'] as $o)
        <details class="s-card"><summary>« {{ $o['q'] }} »</summary><p>{{ $o['a'] }}</p></details>
    @endforeach
</div>
@if (! empty($playbook['check']))
    <div class="alert alert-warning"><x-icon name="shield-check" /><div><strong>À vérifier avant d'envoyer :</strong><ul class="pb-check">@foreach ($playbook['check'] as $c)<li>{{ $c }}</li>@endforeach</ul></div></div>
@endif

<h2 class="pb-h2" id="emails">Les 10 e-mails, dans l'ordre</h2>
<p class="s-sub">Les e-mails 1 à 3 peuvent partir à plusieurs entreprises d'un coup. Les e-mails 4 à 10 sont personnels : remplacez chaque <mark>[à compléter …]</mark> avant l'envoi (le module refuse d'envoyer tant qu'il en reste).</p>
<ol class="pb-emails">
    @foreach ($playbook['emails'] as $email)
        <li class="s-card pb-email" id="{{ $email['key'] }}">
            <div class="pb-email-head">
                <span class="pb-step">{{ $email['step'] }}</span>
                <div><strong>{{ $email['label'] }}</strong><span class="pb-when"><x-icon name="clock" /> {{ $email['when'] }}</span></div>
                <span @class(['pb-tag', 'mass' => $email['mass']])>{{ $email['mass'] ? 'À plusieurs' : 'Personnel' }}</span>
            </div>
            <p class="pb-subject"><span>Objet :</span> {{ $email['subject'] }}</p>
            <pre class="pb-body" id="body-{{ $email['key'] }}">{{ $email['body'] }}</pre>
            <p class="pb-why"><x-icon name="sparkles" /> <span><strong>Pourquoi ça marche :</strong> {{ $email['why'] }}</span></p>
            <div class="pb-actions">
                <button type="button" class="btn btn-sm" data-copy="body-{{ $email['key'] }}" data-copy-subject="{{ $email['subject'] }}"><x-icon name="copy" /> Copier</button>
                @if ($email['mass'])
                    @can('campaigns.manage')<a class="btn btn-sm btn-primary" href="{{ route('pm.simple-email.create', array_filter(['modele' => $email['key'], 'vu' => $sector ? 1 : null, 'sector_id' => $sector?->id])) }}"><x-icon name="send" /> Envoyer à plusieurs entreprises</a>@endcan
                @endif
            </div>
        </li>
    @endforeach
</ol>
<p class="s-hint">Pour envoyer un e-mail personnel : ouvrez la fiche du prospect, cliquez sur le bouton e-mail, puis choisissez le modèle dans la liste.</p>
@endsection
