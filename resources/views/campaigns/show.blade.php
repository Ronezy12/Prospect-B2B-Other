@extends('layouts.app')
@section('title', $campaign->name)

@section('content')
@php($total = $counts->sum())
@php($accepted = (int) ($counts['accepte'] ?? 0))
@php($pending = (int) ($counts['en_attente'] ?? 0) + (int) ($counts['envoi'] ?? 0))
<div class="page-header" @if ($campaign->status === 'en_cours') data-refresh-seconds="10" @endif>
    <div>
        <div class="breadcrumb"><a href="{{ route('pm.campaigns.index') }}">Campagnes</a></div>
        <h1>{{ $campaign->name }}</h1>
        <p class="subtitle">@include('partials.campaign-status', ['status' => $campaign->status]) {{ $campaign->typeLabel() }}
            @if ($campaign->scheduled_at) · {{ $campaign->status === 'programmee' ? 'programmée le' : 'lancée le' }} {{ $campaign->scheduled_at->format('d/m/Y à H:i') }}@endif
            @if ($campaign->templateVersion) · modèle figé en v{{ $campaign->templateVersion->version }}@endif
        </p>
    </div>
    <div class="actions">
        @can('campaigns.manage')
            @if ($campaign->isEditable())<a class="btn" href="{{ route('pm.campaigns.edit', $campaign) }}"><x-icon name="pencil" /> Modifier</a>@endif
            @if ($campaign->type !== 'individuel')
                <form method="POST" action="{{ route('pm.campaigns.duplicate', $campaign) }}">@csrf<button type="submit" class="btn"><x-icon name="layers" /> Dupliquer</button></form>
            @endif
        @endcan
        @can('campaigns.send')
            @if (in_array($campaign->status, ['programmee', 'en_cours']))
                <form method="POST" action="{{ route('pm.campaigns.pause', $campaign) }}">@csrf<button type="submit" class="btn"><x-icon name="clock" /> Mettre en pause</button></form>
            @endif
            @if ($campaign->status === 'en_pause')
                <form method="POST" action="{{ route('pm.campaigns.resume', $campaign) }}">@csrf<button type="submit" class="btn btn-primary"><x-icon name="arrow-right-left" /> Reprendre</button></form>
            @endif
            @if (in_array($campaign->status, \App\Models\Campaign::ACTIVE))
                <form method="POST" action="{{ route('pm.campaigns.cancel', $campaign) }}" data-confirm="Annuler définitivement ? Les e-mails non encore partis ne seront pas envoyés.">@csrf<button type="submit" class="btn btn-danger"><x-icon name="x" /> Arrêter la campagne</button></form>
            @endif
        @endcan
        @can('campaigns.manage')
            @if ($campaign->isEditable())
                <form method="POST" action="{{ route('pm.campaigns.destroy', $campaign) }}" data-confirm="Supprimer ce brouillon ?">@csrf @method('DELETE')<button type="submit" class="btn btn-ghost"><x-icon name="trash-2" /></button></form>
            @endif
        @endcan
    </div>
</div>

@if ($total > 0)
    <div class="stats">
        <div class="stat"><span class="stat-icon"><x-icon name="users" /></span><div><div class="value">{{ $total }}</div><div class="label">Destinataires figés</div></div></div>
        <div class="stat"><span class="stat-icon green"><x-icon name="circle-check" /></span><div><div class="value">{{ $accepted }}</div><div class="label">Acceptés par le serveur d'envoi</div></div></div>
        <div class="stat"><span class="stat-icon blue"><x-icon name="clock" /></span><div><div class="value">{{ $pending }}</div><div class="label">En attente</div></div></div>
        <div class="stat"><span class="stat-icon pink"><x-icon name="shield-check" /></span><div><div class="value">{{ (int) ($counts['exclu'] ?? 0) }}</div><div class="label">Exclus (conformité)</div></div></div>
        <div class="stat"><span class="stat-icon orange"><x-icon name="circle-alert" /></span><div><div class="value">{{ (int) ($counts['echec'] ?? 0) + (int) ($counts['a_verifier'] ?? 0) }}</div><div class="label">Échecs / à vérifier</div></div></div>
    </div>
    <progress class="import-progress" max="{{ max(1, $total - (int) ($counts['exclu'] ?? 0)) }}" value="{{ $accepted + (int) ($counts['echec'] ?? 0) }}" aria-label="Progression de l'envoi"></progress>
@endif

<div class="grid grid-sidebar section-gap">
    <div class="stack">
        <section class="card">
            <h2>Paramètres</h2>
            <dl class="details">
                <dt>Modèle</dt><dd>@if ($campaign->template)<a href="{{ route('pm.templates.show', $campaign->template) }}">{{ $campaign->template->name }}</a>@else — @endif · objet : « {{ ($campaign->templateVersion ?? $campaign->template?->latestVersion)?->subject }} »</dd>
                <dt>Compte d'envoi</dt><dd>{{ $campaign->mailAccount?->name ?? '—' }} @if ($campaign->mailAccount)<span class="muted">({{ $campaign->mailAccount->driverLabel() }})</span>@endif</dd>
                <dt>Expéditeur</dt><dd>{{ $campaign->from_name ?: $campaign->mailAccount?->from_name }} &lt;{{ $campaign->mailAccount?->from_email }}&gt;</dd>
                <dt>Destinataires</dt><dd>
                    @if ($campaign->type === 'newsletter') Liste « {{ $campaign->list?->name }} »
                    @elseif ($campaign->type === 'individuel') E-mail individuel
                    @else Segment « {{ $campaign->segment?->name }} » @if ($campaign->segment)(<a href="{{ route('pm.segments.edit', $campaign->segment) }}">voir</a>)@endif
                    @endif
                </dd>
                @if ($campaign->exclusions)<dt>Exclusions</dt><dd>@if (! empty($campaign->exclusions['tag_ids'])){{ count($campaign->exclusions['tag_ids']) }} tag(s) @endif @if (! empty($campaign->exclusions['contacted_within_days'])) · contactés depuis {{ $campaign->exclusions['contacted_within_days'] }} j @endif</dd>@endif
                <dt>Créée par</dt><dd>{{ $campaign->author?->name ?? '—' }} le {{ $campaign->created_at->format('d/m/Y') }}</dd>
            </dl>
        </section>

        @if ($total > 0)
            <section class="card">
                <h2>Statistiques</h2>
                <ul class="bar-list">
                    @foreach (['accepte', 'en_attente', 'echec', 'exclu', 'annule', 'a_verifier'] as $s)
                        @if ($counts[$s] ?? 0)
                            <li><span>{{ config('prospection.recipient_statuses')[$s] }}</span><span class="val">{{ $counts[$s] }}</span><progress max="{{ $total }}" value="{{ $counts[$s] }}"></progress></li>
                        @endif
                    @endforeach
                </ul>
                @if ($exclusions->isNotEmpty())
                    <h3 class="section-gap">Motifs d'exclusion</h3>
                    <ul class="small">@foreach ($exclusions as $reason => $n)<li>{{ config('prospection.exclusion_reasons')[$reason] ?? $reason }} : {{ $n }}</li>@endforeach</ul>
                @endif
                <div class="alert alert-info section-gap"><x-icon name="circle-alert" /><span>
                    « Accepté » signifie que le serveur d'envoi a pris le message en charge, pas qu'il a été livré.
                    Livraisons, rebonds, ouvertures et clics : @if ($reportsEvents) remontés par le fournisseur via webhooks à partir de la phase 7. @else <strong>indisponibles</strong> avec ce type de compte (SMTP / journal), qui ne renvoie pas ces événements. @endif
                    Désinscriptions enregistrées : {{ (int) ($events['desabonnement'] ?? 0) }}.
                </span></div>
            </section>

            <section class="card flush">
                <div class="card-head">
                    <h2>Destinataires</h2>
                    <form method="GET" class="toolbar">
                        <select name="statut" aria-label="Filtrer par statut"><option value="">Tous</option>@foreach (config('prospection.recipient_statuses') as $k => $l)<option value="{{ $k }}" @selected(request('statut') === $k)>{{ $l }}</option>@endforeach</select>
                        <button type="submit" class="btn btn-sm">Filtrer</button>
                    </form>
                </div>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Adresse</th><th>Statut</th><th>Détail</th><th>Envoyé</th></tr></thead>
                        <tbody>
                        @foreach ($recipients as $r)
                            <tr>
                                <td>{{ $r->email }}<div class="sub">{{ trim(($r->merge_data['prenom'] ?? '').' '.($r->merge_data['nom'] ?? '')) }} · {{ $r->merge_data['entreprise'] ?? '' }}</div></td>
                                <td><span @class(['badge', 'badge-success' => $r->status === 'accepte', 'badge-danger' => in_array($r->status, ['echec', 'a_verifier']), 'badge-warning' => $r->status === 'exclu'])>{{ config('prospection.recipient_statuses')[$r->status] ?? $r->status }}</span></td>
                                <td class="small">{{ $r->exclusion_reason ? (config('prospection.exclusion_reasons')[$r->exclusion_reason] ?? $r->exclusion_reason) : $r->last_error }}@if ($r->attempts > 1) · {{ $r->attempts }} tentatives @endif</td>
                                <td class="nowrap small">{{ $r->sent_at?->format('d/m H:i') ?? '—' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                {{ $recipients->links('partials.pagination') }}
            </section>
        @endif
    </div>

    <div class="stack">
        @if ($campaign->isEditable())
            @can('campaigns.manage')
                <section class="card">
                    <h2><x-icon name="mail" /> 1. Envoyer un test</h2>
                    <form method="POST" action="{{ route('pm.campaigns.test', $campaign) }}">
                        @csrf
                        <div class="field"><label for="emails">Destinataires du test (5 max)</label><input type="text" id="emails" name="emails" value="{{ old('emails', auth()->user()->email) }}" required>@error('emails')<div class="error">{{ $message }}</div>@enderror</div>
                        <p class="small muted">Contenu réel avec des données d'exemple, objet préfixé [TEST]. @if ($campaign->last_test_at)Dernier test : {{ $campaign->last_test_at->format('d/m/Y H:i') }}.@endif</p>
                        <div class="form-actions"><button type="submit" class="btn"><x-icon name="mail" /> Envoyer le test</button></div>
                    </form>
                </section>
            @endcan

            @if ($preflight)
                <section class="card">
                    <h2><x-icon name="shield-check" /> 2. Vérification avant envoi</h2>
                    <ul class="checklist">
                        @foreach ($preflight['errors'] as $e)<li><span class="ko"><x-icon name="circle-alert" /></span> {{ $e }}</li>@endforeach
                        @foreach ($preflight['warnings'] as $w)<li><span class="warn"><x-icon name="circle-alert" /></span> {{ $w }}</li>@endforeach
                        @if (! $preflight['errors'])
                            <li><span class="ok"><x-icon name="circle-check" /></span> {{ $preflight['estimate']['eligible'] }} destinataire(s) autorisé(s) sur {{ $preflight['estimate']['total'] }} ciblé(s).</li>
                            @foreach ($preflight['estimate']['excluded'] as $reason => $n)<li><span class="warn"><x-icon name="shield-check" /></span> {{ $n }} exclu(s) : {{ config('prospection.exclusion_reasons')[$reason] ?? $reason }}</li>@endforeach
                            @if ($campaign->isMarketing())<li><span class="ok"><x-icon name="circle-check" /></span> Lien de désinscription et en-tête « désinscription en un clic » ajoutés à chaque e-mail.</li>@endif
                        @endif
                    </ul>
                </section>

                @unless ($preflight['errors'])
                    <form method="POST" action="{{ route('pm.campaigns.schedule', $campaign) }}" class="card">
                        @csrf
                        <h2><x-icon name="calendar-clock" /> 3. Programmer l'envoi</h2>
                        <div class="choice-list">
                            <label class="checkbox"><input type="radio" name="when" value="now" @checked(old('when', 'now') === 'now')> Envoyer maintenant</label>
                            <label class="checkbox"><input type="radio" name="when" value="later" @checked(old('when') === 'later')> Programmer le…</label>
                        </div>
                        <div class="field section-gap"><label for="scheduled_at">Date et heure</label><input type="datetime-local" id="scheduled_at" name="scheduled_at" value="{{ old('scheduled_at', now()->addDay()->setTime(9, 30)->format('Y-m-d\TH:i')) }}">@error('scheduled_at')<div class="error">{{ $message }}</div>@enderror</div>
                        <label class="checkbox section-gap"><input type="checkbox" name="confirm_compliance" value="1" required> Je confirme que le contenu correspond à la finalité choisie et que les destinataires ont été informés du traitement de leurs données.</label>
                        @error('campaign')<div class="error">{{ $message }}</div>@enderror
                        <div class="form-actions"><button type="submit" class="btn btn-primary"><x-icon name="mail" /> Valider</button></div>
                    </form>
                @endunless
            @endif
        @endif
    </div>
</div>
@endsection
