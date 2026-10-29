@extends('layouts.app')
@section('title', $campaign->exists ? 'Modifier la campagne' : 'Nouvelle campagne')

@section('content')
@php($type = old('type', $campaign->type))
<div class="page-header">
    <div>
        <div class="breadcrumb"><a href="{{ route('pm.campaigns.index') }}">Campagnes</a></div>
        <h1>{{ $campaign->exists ? $campaign->name : 'Nouvelle campagne' }}</h1>
        <p class="subtitle">{{ config('prospection.campaign_types')[$type] ?? '' }}</p>
    </div>
</div>

@unless ($campaign->exists)
    <nav class="tabs" aria-label="Type de campagne">
        @foreach (['prospection', 'newsletter', 'operationnel'] as $t)
            <a href="{{ route('pm.campaigns.create', ['type' => $t]) }}" @class(['tab', 'active' => $type === $t])>{{ config('prospection.campaign_types')[$t] }}</a>
        @endforeach
    </nav>
@endunless

<form method="POST" action="{{ $campaign->exists ? route('pm.campaigns.update', $campaign) : route('pm.campaigns.store') }}" class="card">
    @csrf
    @if ($campaign->exists) @method('PUT') @else <input type="hidden" name="type" value="{{ $type }}"> @endif

    <div class="alert alert-info"><x-icon name="shield-check" /><span>
        @if ($type === 'prospection') Prospection B2B : adresses professionnelles, offre en rapport avec la fonction, lien d'opposition dans chaque message. Les particuliers sans consentement sont exclus automatiquement.
        @elseif ($type === 'newsletter') Newsletter : seuls les abonnés de la liste (consentement tracé) la reçoivent.
        @else Communication opérationnelle : information de service (rendez-vous, facture, intervention), sans contenu promotionnel. Les désinscriptions marketing ne la bloquent pas.
        @endif
    </span></div>

    <div class="form-grid">
        <div class="field full"><label for="name">Nom de la campagne *</label><input type="text" id="name" name="name" value="{{ old('name', $campaign->name) }}" required maxlength="150"></div>
        <div class="field">
            <label for="template_id">Modèle *</label>
            <select id="template_id" name="template_id" required>
                <option value="">—</option>
                @foreach ($templates->where('category', $type) as $t)<option value="{{ $t->id }}" @selected(old('template_id', $campaign->template_id) == $t->id)>{{ $t->name }}</option>@endforeach
            </select>
            @error('template_id')<div class="error">{{ $message }}</div>@enderror
            @if ($templates->where('category', $type)->isEmpty())<div class="hint">Aucun modèle de cette catégorie : <a href="{{ route('pm.templates.create') }}">créez-en un</a>.</div>@endif
        </div>
        <div class="field">
            <label for="mail_account_id">Compte d'envoi *</label>
            <select id="mail_account_id" name="mail_account_id" required>
                <option value="">—</option>
                @foreach ($accounts as $a)<option value="{{ $a->id }}" @selected(old('mail_account_id', $campaign->mail_account_id) == $a->id)>{{ $a->name }} ({{ $a->from_email }})</option>@endforeach
            </select>
        </div>
        @if ($type === 'newsletter')
            <div class="field">
                <label for="list_id">Liste d'abonnés *</label>
                <select id="list_id" name="list_id" required><option value="">—</option>@foreach ($lists as $l)<option value="{{ $l->id }}" @selected(old('list_id', $campaign->list_id) == $l->id)>{{ $l->name }}</option>@endforeach</select>
            </div>
        @else
            <div class="field">
                <label for="segment_id">Segment de destinataires *</label>
                <select id="segment_id" name="segment_id" required><option value="">—</option>@foreach ($segments as $s)<option value="{{ $s->id }}" @selected(old('segment_id', $campaign->segment_id) == $s->id)>{{ $s->name }}</option>@endforeach</select>
                @if ($segments->isEmpty())<div class="hint"><a href="{{ route('pm.segments.create') }}">Créer un segment</a></div>@endif
            </div>
        @endif
        <div class="field"><label for="from_name">Nom d'expéditeur affiché</label><input type="text" id="from_name" name="from_name" value="{{ old('from_name', $campaign->from_name) }}" maxlength="120" placeholder="Celui du compte d'envoi par défaut"></div>
        <div class="field"><label for="reply_to">Adresse de réponse</label><input type="email" id="reply_to" name="reply_to" value="{{ old('reply_to', $campaign->reply_to) }}" maxlength="190"></div>
    </div>

    @if ($type !== 'newsletter')
        <fieldset class="section-gap">
            <legend><x-icon name="x" /> Exclusions</legend>
            <div class="form-grid">
                <div class="field">
                    <label for="ex-tags">Exclure les entreprises portant ces tags</label>
                    <select id="ex-tags" name="exclusions[tag_ids][]" multiple size="4">
                        @foreach ($tags as $t)<option value="{{ $t->id }}" @selected(in_array($t->id, (array) old('exclusions.tag_ids', $campaign->exclusions['tag_ids'] ?? [])))>{{ $t->name }}</option>@endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="ex-days">Exclure les contacts déjà sollicités depuis (jours)</label>
                    <input type="number" id="ex-days" name="exclusions[contacted_within_days]" value="{{ old('exclusions.contacted_within_days', $campaign->exclusions['contacted_within_days'] ?? '') }}" min="0" max="365" placeholder="ex. 30">
                    <div class="hint">Limite la pression commerciale : un contact ayant reçu un e-mail sur cette période est écarté.</div>
                </div>
            </div>
        </fieldset>
    @endif

    <fieldset class="section-gap">
        <legend><x-icon name="chart-column" /> Mesure d'audience</legend>
        @php($trackOpens = \App\Models\Setting::get('tracking.opens', false))
        @php($trackClicks = \App\Models\Setting::get('tracking.clicks', false))
        @if (! $trackOpens && ! $trackClicks)
            <p class="small muted">Le suivi des ouvertures et des clics est désactivé pour toute l'application (Paramètres › Suivi des e-mails).</p>
        @else
            <p class="small muted">Appliqué uniquement aux contacts ayant consenti à la mesure d'audience (recommandation CNIL 2026). Une ouverture n'est jamais une preuve de lecture.</p>
            <div class="choice-list">
                @if ($trackOpens)<label class="checkbox"><input type="hidden" name="track_opens" value="0"><input type="checkbox" name="track_opens" value="1" @checked(old('track_opens', $campaign->track_opens))> Mesurer les ouvertures (pixel)</label>@endif
                @if ($trackClicks)<label class="checkbox"><input type="hidden" name="track_clicks" value="0"><input type="checkbox" name="track_clicks" value="1" @checked(old('track_clicks', $campaign->track_clicks))> Mesurer les clics (liens de redirection)</label>@endif
            </div>
        @endif
    </fieldset>

    <div class="form-actions">
        <a class="btn" href="{{ $campaign->exists ? route('pm.campaigns.show', $campaign) : route('pm.campaigns.index') }}">Annuler</a>
        <button type="submit" class="btn btn-primary"><x-icon name="check" /> Enregistrer le brouillon</button>
    </div>
</form>
@endsection
