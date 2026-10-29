@extends('layouts.app')
@section('title', 'Campagnes')

@section('content')
<div class="page-header">
    <div>
        <h1>Campagnes e-mail</h1>
        <p class="subtitle">Prospection, newsletters et communications opérationnelles</p>
    </div>
    @can('campaigns.manage')
        <div class="actions">
            <a class="btn" href="{{ route('pm.campaigns.create', ['type' => 'newsletter']) }}"><x-icon name="newspaper" /> Newsletter</a>
            <a class="btn btn-primary" href="{{ route('pm.campaigns.create') }}"><x-icon name="plus" /> Nouvelle campagne</a>
        </div>
    @endcan
</div>

<nav class="tabs" aria-label="Types de campagne">
    <a href="{{ route('pm.campaigns.index') }}" @class(['tab', 'active' => ! $type])>Toutes</a>
    @foreach (config('prospection.campaign_types') as $key => $label)
        <a href="{{ route('pm.campaigns.index', ['type' => $key]) }}" @class(['tab', 'active' => $type === $key])>{{ $label }}</a>
    @endforeach
</nav>

<div class="card flush">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Campagne</th><th>Type</th><th>Statut</th><th>Programmation</th><th>Acceptés</th><th>En attente</th><th>Compte</th></tr></thead>
            <tbody>
            @forelse ($campaigns as $c)
                <tr>
                    <td><a class="row-title" href="{{ route('pm.campaigns.show', $c) }}">{{ $c->name }}</a><div class="sub">{{ $c->template?->name }}</div></td>
                    <td><span class="badge plain">{{ $c->typeLabel() }}</span></td>
                    <td>@include('partials.campaign-status', ['status' => $c->status])</td>
                    <td class="nowrap small">{{ $c->scheduled_at?->format('d/m/Y H:i') ?? '—' }}</td>
                    <td class="num">{{ $c->accepted_count }}</td>
                    <td class="num">{{ $c->pending_count }}</td>
                    <td class="small">{{ $c->mailAccount?->name ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="table-empty"><div class="empty-state"><x-icon name="mail" /><strong>Aucune campagne</strong>Créez un modèle, un segment, puis votre première campagne.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $campaigns->links('partials.pagination') }}
</div>
@endsection
