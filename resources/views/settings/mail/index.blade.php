@extends('layouts.app')
@section('title', 'Comptes d\'envoi')

@section('content')
<div class="page-header">
    <div>
        <h1>Comptes d'envoi</h1>
        <p class="subtitle">Serveurs SMTP et fournisseurs API utilisés pour les campagnes. Les identifiants sont chiffrés et ne sont jamais réaffichés.</p>
    </div>
    <div class="actions"><a class="btn btn-primary" href="{{ route('pm.settings.mail.create') }}"><x-icon name="plus" /> Nouveau compte</a></div>
</div>

<div class="card flush">
    @forelse ($accounts as $account)
        <div class="settings-row">
            <div class="grow">
                <a class="row-title" href="{{ route('pm.settings.mail.edit', $account) }}">{{ $account->name }}</a>
                <div class="sub">{{ $account->driverLabel() }} · {{ $account->from_name }} &lt;{{ $account->from_email }}&gt; · {{ $account->hourly_limit }}/h, {{ $account->daily_limit }}/jour</div>
            </div>
            @if (! $account->is_active)
                <span class="badge">Désactivé</span>
            @elseif ($account->last_test_result === 'ok')
                <span class="badge badge-success">Testé le {{ $account->last_tested_at->format('d/m/Y') }}</span>
            @elseif ($account->last_test_result)
                <span class="badge badge-danger" title="{{ $account->last_test_result }}">Dernier test en échec</span>
            @else
                <span class="badge badge-warning">À tester</span>
            @endif
            <form method="POST" action="{{ route('pm.settings.mail.test', $account) }}" class="inline">
                @csrf
                <label class="sr-only" for="test-{{ $account->id }}">Adresse de test</label>
                <input type="email" id="test-{{ $account->id }}" name="test_email" value="{{ auth()->user()->email }}" required>
                <button type="submit" class="btn btn-sm"><x-icon name="mail" /> Envoyer un test</button>
            </form>
        </div>
    @empty
        <div class="empty-state"><x-icon name="inbox" /><strong>Aucun compte d'envoi</strong>Ajoutez votre serveur SMTP (ou le mode « journal local » pour essayer sans rien envoyer).</div>
    @endforelse
</div>

<section class="card">
    <h2>Avant le premier envoi réel</h2>
    <ul class="small">
        <li>Authentifiez votre domaine d'expédition : enregistrements DNS <strong>SPF</strong>, <strong>DKIM</strong> et <strong>DMARC</strong> (fournis par votre hébergeur de messagerie ou votre prestataire).</li>
        <li>Utilisez une adresse d'expédition de votre domaine, jamais une adresse gratuite (gmail, outlook…).</li>
        <li>Démarrez avec des limites basses (quelques centaines par jour) pour bâtir la réputation du domaine.</li>
        <li>« Accepté » signifie que le serveur a pris le message en charge, pas qu'il a été livré ou lu.</li>
    </ul>
</section>
@endsection
