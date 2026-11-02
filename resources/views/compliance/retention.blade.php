@extends('layouts.app')
@section('title', 'Conservation des données')

@section('content')
<div class="page-header">
    <div>
        <h1>Conservation des données</h1>
        <p class="subtitle">Les données ne sont pas gardées indéfiniment : ces règles s'appliquent toutes seules, chaque nuit.</p>
    </div>
</div>

<div class="grid grid-sidebar">
    <section class="card">
        <h2>Les règles</h2>
        <ul class="rule-list plain">
            <li><strong>Prospect sans aucun échange depuis {{ $rules['inactive_prospect_months'] }} mois</strong> (pas d'appel, de note, de réponse ni d'affaire en cours) : ses interlocuteurs sont anonymisés et la fiche est archivée. Les clients ne sont jamais concernés.</li>
            <li><strong>Ouvertures et clics</strong> des e-mails : effacés après {{ $rules['tracking_months'] }} mois.</li>
            <li><strong>Notifications lues</strong> : supprimées après {{ $rules['notifications_months'] }} mois.</li>
            <li><strong>Journal d'audit</strong> : conservé {{ $rules['audit_months'] }} mois, puis purgé.</li>
            <li><strong>Liste d'opposition</strong> : conservée sans limite (empreinte seulement), pour ne jamais recontacter une personne qui a refusé.</li>
            <li><strong>Fichiers d'import</strong> : supprimés après {{ config('prospection.import.retention_days') }} jours.</li>
        </ul>
        <p class="small muted">Durées par défaut inspirées des recommandations de la CNIL pour la prospection (3 ans après le dernier contact). Elles se règlent dans le fichier de configuration (variables <code>PM_RETENTION_*</code>) et doivent être validées par votre DPO.</p>
    </section>

    <aside class="card">
        <h2>Prochain passage</h2>
        <dl class="details small">
            <dt>Prospects à archiver</dt><dd>{{ $preview['prospects_inactifs'] }}</dd>
            <dt>Suivis à effacer</dt><dd>{{ $preview['suivis_effaces'] }}</dd>
            <dt>Événements à supprimer</dt><dd>{{ $preview['evenements_supprimes'] }}</dd>
            <dt>Notifications</dt><dd>{{ $preview['notifications_supprimees'] }}</dd>
            <dt>Journal</dt><dd>{{ $preview['journal_purge'] }}</dd>
        </dl>
        <p class="small muted">Dernier passage : {{ $lastRun ? \Illuminate\Support\Carbon::parse($lastRun['le'])->format('d/m/Y H:i') : 'jamais' }}</p>
        <form method="POST" action="{{ route('pm.compliance.retention.apply') }}" data-confirm="Appliquer maintenant les durées de conservation ?">
            @csrf
            <label class="checkbox"><input type="checkbox" name="confirm" value="1" required> J'ai vérifié les chiffres ci-dessus</label>
            <div class="form-actions"><button type="submit" class="btn">Appliquer maintenant</button></div>
        </form>
    </aside>
</div>
@endsection
