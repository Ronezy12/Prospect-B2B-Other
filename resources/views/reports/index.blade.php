@extends('layouts.app')
@section('title', 'Statistiques et rapports')

@section('content')
@php($base = max(1, $email['acceptes']))
<div class="page-header">
    <div>
        <h1>Statistiques et rapports</h1>
        <p class="subtitle">Depuis le {{ $since->format('d/m/Y') }} · chiffres issus des événements enregistrés</p>
    </div>
    <nav class="tabs" aria-label="Période">
        @foreach (\App\Http\Controllers\ReportController::PERIODS as $d => $label)
            <a href="{{ route('pm.reports.index', ['jours' => $d]) }}" @class(['tab', 'active' => $days === $d])>{{ $label }}</a>
        @endforeach
    </nav>
</div>

<div class="stats">
    <div class="stat"><span class="stat-icon"><x-icon name="building-2" /></span><div><div class="value">{{ $commercial['nouveaux_prospects'] }}</div><div class="label">Nouveaux prospects</div></div></div>
    <div class="stat"><span class="stat-icon blue"><x-icon name="briefcase" /></span><div><div class="value">{{ $commercial['opportunites_creees'] }}</div><div class="label">Opportunités créées</div></div></div>
    <div class="stat"><span class="stat-icon green"><x-icon name="circle-check" /></span><div><div class="value">{{ $commercial['opportunites_gagnees'] }}</div><div class="label">Opportunités gagnées</div></div></div>
    <div class="stat"><span class="stat-icon pink"><x-icon name="trending-up" /></span><div><div class="value">{{ $commercial['conversions_clients'] }}</div><div class="label">Conversions en clients</div></div></div>
    <div class="stat"><span class="stat-icon orange"><x-icon name="target" /></span><div><div class="value">{{ number_format($commercial['montant_gagne'], 0, ',', ' ') }} €</div><div class="label">Montant gagné</div></div></div>
</div>

<div class="grid grid-sidebar">
    <section class="card">
        <h2>Entonnoir e-mail</h2>
        <div class="funnel">
            @php($rows = [
                ['Envois demandés', $email['demandes'], true, null],
                ['Acceptés par le serveur d\'envoi', $email['acceptes'], true, null],
                ['Livrés (confirmés)', $email['livres'], $availability['delivery'], 'Nécessite un fournisseur API avec webhooks'],
                ['Ouvertures détectées (indicatif)', $email['ouvertures'], $availability['opens'], 'Suivi désactivé (consentement requis)'],
                ['Clics', $email['clics'], $availability['clicks'], 'Suivi désactivé (consentement requis)'],
                ['Réponses', $email['reponses'], $availability['replies'], 'Aucune boîte IMAP configurée'],
            ])
            @foreach ($rows as [$label, $value, $available, $why])
                <div @class(['funnel-row', 'na' => ! $available])>
                    <span>{{ $label }}</span>
                    <progress class="funnel-bar" max="{{ max(1, $email['demandes']) }}" value="{{ $available ? $value : 0 }}" aria-label="{{ $label }}"></progress>
                    <span class="figure">@if ($available){{ $value }}@if ($email['acceptes'] && $label !== 'Envois demandés') <span class="muted small">({{ round(100 * $value / $base) }} %)</span>@endif @else Indisponible @endif</span>
                </div>
                @unless ($available)<div class="kpi-unavailable">{{ $why }}</div>@endunless
            @endforeach
        </div>
    </section>

    <section class="card">
        <h2>Qualité de la base</h2>
        <dl class="details">
            <dt>Exclus (conformité)</dt><dd>{{ $email['exclus'] }}</dd>
            <dt>Échecs d'envoi</dt><dd>{{ $email['echecs'] }}</dd>
            <dt>Rebonds définitifs</dt><dd>@if ($availability['delivery']){{ $email['rebonds_definitifs'] }}@else<span class="muted">Indisponible</span>@endif</dd>
            <dt>Rebonds temporaires</dt><dd>@if ($availability['delivery']){{ $email['rebonds_temporaires'] }}@else<span class="muted">Indisponible</span>@endif</dd>
            <dt>Signalés indésirables</dt><dd>@if ($availability['delivery']){{ $email['plaintes'] }}@else<span class="muted">Indisponible</span>@endif</dd>
            <dt>Désinscriptions</dt><dd>{{ $email['desabonnements'] }}</dd>
        </dl>
        <p class="small muted section-gap">Les rebonds définitifs, plaintes et désinscriptions alimentent automatiquement la liste d'opposition.</p>
    </section>
</div>

<section class="card flush section-gap">
    <div class="card-head"><h2>Par campagne</h2></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Campagne</th><th>Acceptés</th><th>Livrés</th><th>Rebonds</th><th>Ouvertures</th><th>Clics</th><th>Réponses</th><th>Désinscriptions</th></tr></thead>
            <tbody>
            @forelse ($campaigns as $c)
                @php($events = $c->mailAccount?->driver === 'brevo')
                <tr>
                    <td><a class="row-title" href="{{ route('pm.campaigns.show', $c) }}">{{ $c->name }}</a><div class="sub">{{ $c->typeLabel() }} · {{ $c->started_at?->format('d/m/Y') ?? 'non lancée' }}</div></td>
                    <td class="num">{{ $c->acceptes }}</td>
                    <td class="num">{!! $events ? e($c->livres) : '<span class="muted">n.d.</span>' !!}</td>
                    <td class="num">{!! $events ? e($c->rebonds) : '<span class="muted">n.d.</span>' !!}</td>
                    <td class="num">{!! $c->track_opens ? e($c->ouvertures) : '<span class="muted">non mesuré</span>' !!}</td>
                    <td class="num">{!! $c->track_clicks ? e($c->clics) : '<span class="muted">non mesuré</span>' !!}</td>
                    <td class="num">{!! $availability['replies'] ? e($c->reponses) : '<span class="muted">n.d.</span>' !!}</td>
                    <td class="num">{{ $c->desabonnements }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="table-empty">Aucune campagne sur la période.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <p class="small muted card-title">n.d. : non disponible avec ce type de compte d'envoi (SMTP ou journal local ne renvoient pas d'événements de livraison).</p>
</section>
<section class="card">
    <h2><x-icon name="share-2" /> Réseaux sociaux</h2>
    <p class="small muted">Chiffres issus des actions consignées par l'équipe (et des messages reçus via l'API Meta) : ils reflètent ce qui a été saisi, pas une mesure faite par les plateformes.</p>
    <div class="stats">
        <div class="stat"><div><div class="value">{{ $social['par_type']['invitation_envoyee'] ?? 0 }}</div><div class="label">Invitations envoyées</div></div></div>
        <div class="stat"><div><div class="value">{{ $social['par_type']['message_envoye'] ?? 0 }}</div><div class="label">Messages envoyés</div></div></div>
        <div class="stat"><div><div class="value">{{ $social['par_type']['message_recu'] ?? 0 }}</div><div class="label">Réponses reçues</div></div></div>
        <div class="stat"><div><div class="value">{{ $social['par_type']['rdv'] ?? 0 }}</div><div class="label">Rendez-vous fixés</div></div></div>
        <div class="stat"><div><div class="value">@if ($social['contactes']){{ round(100 * $social['ont_repondu'] / $social['contactes']) }} %@else — @endif</div><div class="label">Taux de réponse déclaré ({{ $social['ont_repondu'] }} / {{ $social['contactes'] }} profils sollicités)</div></div></div>
    </div>
    @if ($social['par_plateforme'])
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Réseau</th>@foreach (config('prospection.social.statuses') as $label)<th class="num">{{ $label }}</th>@endforeach</tr></thead>
                <tbody>
                @foreach ($social['par_plateforme'] as $platform => $byStatus)
                    <tr><td>@include('social.partials.platform', ['platform' => $platform])</td>@foreach (array_keys(config('prospection.social.statuses')) as $status)<td class="num">{{ $byStatus[$status] ?? 0 }}</td>@endforeach</tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="muted small">Aucun profil suivi pour l'instant.</p>
    @endif
</section>
@endsection
