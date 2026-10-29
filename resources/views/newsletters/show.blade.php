@extends('layouts.app')
@section('title', $list->name)

@section('content')
<div class="page-header">
    <div>
        <div class="breadcrumb"><a href="{{ route('pm.newsletters.index') }}">Newsletters</a></div>
        <h1>{{ $list->name }}</h1>
        <p class="subtitle">{{ $list->description ?: 'Liste d\'abonnés' }}</p>
    </div>
</div>

<div class="grid grid-sidebar">
    <section class="card flush">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Abonné</th><th>Statut</th><th>Consentement</th><th></th></tr></thead>
                <tbody>
                @forelse ($subscriptions as $s)
                    <tr>
                        <td><span class="row-title">{{ $s->contact?->fullName() }}</span><div class="sub">{{ $s->contact?->email }} · {{ $s->contact?->company?->name }}</div></td>
                        <td>@if ($s->status === 'abonne')<span class="badge badge-success">Abonné</span>@else<span class="badge">Désabonné le {{ $s->unsubscribed_at?->format('d/m/Y') }}</span>@endif</td>
                        <td class="small">{{ $s->consent_source }}<div class="sub">{{ $s->consented_at->format('d/m/Y H:i') }}</div></td>
                        <td>
                            @if ($s->status === 'abonne')
                                @can('campaigns.manage')
                                    <form method="POST" action="{{ route('pm.subscriptions.unsubscribe', $s) }}" data-confirm="Désabonner cette personne ?">@csrf<button type="submit" class="btn-link danger small">Désabonner</button></form>
                                @endcan
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="table-empty"><div class="empty-state"><x-icon name="users" /><strong>Aucun abonné</strong>Ajoutez une personne ayant explicitement accepté de recevoir cette newsletter.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $subscriptions->links('partials.pagination') }}
    </section>
    @can('campaigns.manage')
        <form method="POST" action="{{ route('pm.newsletters.subscribe', $list) }}" class="card">
            @csrf
            <h2>Ajouter un abonné</h2>
            <p class="small muted">Réservé aux personnes ayant donné leur accord. La source et la date du consentement sont conservées comme preuve.</p>
            <div class="field"><label for="email">E-mail du contact *</label><input type="email" id="email" name="email" value="{{ old('email') }}" required>@error('email')<div class="error">{{ $message }}</div>@enderror</div>
            <div class="field section-gap"><label for="consent_source">Source du consentement *</label><input type="text" id="consent_source" name="consent_source" value="{{ old('consent_source') }}" required maxlength="255" placeholder="ex. Formulaire salon 2026, case cochée"></div>
            <div class="field section-gap"><label for="consented_at">Date du consentement *</label><input type="datetime-local" id="consented_at" name="consented_at" value="{{ old('consented_at', now()->format('Y-m-d\TH:i')) }}" required></div>
            <div class="form-actions"><button type="submit" class="btn btn-primary"><x-icon name="user-plus" /> Abonner</button></div>
        </form>
    @endcan
</div>
@endsection
