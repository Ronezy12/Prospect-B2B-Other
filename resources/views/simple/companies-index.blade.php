@extends('layouts.app')
@section('title', 'Mes prospects')

@section('content')
<div class="s-head">
    <div>
        <h1>Mes prospects</h1>
        <p class="s-lead">Un clic sur le statut pour le changer, un double-clic sur la carte pour ouvrir la fiche.</p>
    </div>
    @can('prospects.create')<a class="btn btn-primary" href="{{ route('pm.companies.create') }}"><x-icon name="plus" /> Ajouter un prospect</a>@endcan
</div>

<form method="GET" class="s-search">
    @isset($filters['status'])<input type="hidden" name="status" value="{{ $filters['status'] }}">@endisset
    <span class="s-search-field"><x-icon name="search" /><label class="sr-only" for="q">Rechercher</label><input type="search" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Agence, personne, ville, e-mail…"></span>
    <button type="submit" class="btn btn-primary">Rechercher</button>
</form>

<nav class="s-filters" aria-label="Filtrer par statut">
    <a href="{{ route('pm.companies.index', array_filter(['q' => $filters['q'] ?? null])) }}" @class(['s-filter', 'active' => empty($filters['status'])])>Tous <span>{{ $statusCounts->sum() }}</span></a>
    @foreach (config('prospection.company_statuses') as $key => $label)
        <a href="{{ route('pm.companies.index', array_filter(['q' => $filters['q'] ?? null, 'status' => $key])) }}" @class(['s-filter', 'active' => ($filters['status'] ?? null) === $key])>{{ $label }} <span>{{ $statusCounts[$key] ?? 0 }}</span></a>
    @endforeach
</nav>

<div class="pc-grid">
    @forelse ($companies as $company)
        @php($contact = $company->contacts->first())
        @php($phone = $contact?->mobile ?? $contact?->phone ?? $company->phone)
        @php($mobile = $contact?->mobile ?? $phone)
        @php($email = $contact?->email ?? $company->email)
        <article class="pc-card pc-{{ $company->status }}" data-open="{{ route('pm.companies.show', $company) }}" tabindex="0" aria-label="{{ $company->name }} — double-cliquez pour ouvrir la fiche">
            <div class="pc-top">
                <a class="pc-agency" href="{{ route('pm.companies.show', $company) }}">{{ $company->name }}</a>
                <span class="pc-person">{{ $contact?->fullName() ?? '—' }}</span>
            </div>
            <div class="pc-city"><x-icon name="map-pin" /> {{ $company->city ?: 'Ville non renseignée' }}</div>
            @can('update', $company)
                <details class="pc-status-menu">
                    <summary class="pc-status" title="Changer le statut">{{ $company->statusLabel() }} <x-icon name="chevron-right" class="pc-caret" /></summary>
                    <form method="POST" action="{{ route('pm.companies.status', $company) }}" class="pc-status-list">
                        @csrf
                        @foreach (config('prospection.company_statuses') as $key => $label)
                            <button type="submit" name="status" value="{{ $key }}" @class(['pc-status-opt', 'pc-opt-'.$key, 'current' => $company->status === $key])>{{ $label }}</button>
                        @endforeach
                    </form>
                </details>
            @else
                <div class="pc-status">{{ $company->statusLabel() }}</div>
            @endcan
            <div class="pc-actions">
                @if ($email)
                    @if ($contact?->email && auth()->user()->can('emails.individual'))
                        <a class="pc-btn" href="{{ route('pm.contacts.email.create', $contact) }}" title="Envoyer un e-mail" aria-label="Envoyer un e-mail à {{ $contact->fullName() }}"><x-icon name="mail" /></a>
                    @else
                        <a class="pc-btn" href="mailto:{{ $email }}" title="Écrire à {{ $email }}" aria-label="Écrire à {{ $email }}"><x-icon name="mail" /></a>
                    @endif
                @else
                    <span class="pc-btn off" title="Pas d'e-mail renseigné"><x-icon name="mail" /></span>
                @endif
                @if ($phone)
                    <a class="pc-btn call" href="tel:{{ $phone }}" title="Appeler {{ \App\Support\Normalizer::displayPhone($phone) }}" aria-label="Appeler {{ \App\Support\Normalizer::displayPhone($phone) }}"><x-icon name="phone" /></a>
                @else
                    <span class="pc-btn off" title="Pas de téléphone renseigné"><x-icon name="phone" /></span>
                @endif
                @if ($mobile && ($wa = \App\Support\Normalizer::whatsappUrl($mobile)))
                    <a class="pc-btn wa" href="{{ $wa }}" target="_blank" rel="noopener noreferrer" title="Écrire sur WhatsApp" aria-label="Écrire sur WhatsApp"><x-icon name="whatsapp" /></a>
                @else
                    <span class="pc-btn off" title="Pas de numéro pour WhatsApp"><x-icon name="whatsapp" /></span>
                @endif
                @php($social = $company->socialProfiles->first(fn ($p) => ! $p->isOpposed()))
                @if ($social)
                    <a class="pc-btn" href="{{ $social->url }}" target="_blank" rel="noopener noreferrer nofollow" title="Ouvrir le profil {{ $social->platformLabel() }}" aria-label="Ouvrir le profil {{ $social->platformLabel() }}"><x-icon name="share-2" /></a>
                @endif
            </div>
        </article>
    @empty
        <div class="s-empty"><x-icon name="building-2" /><strong>Aucun prospect trouvé</strong>@can('prospects.create')<span><a href="{{ route('pm.companies.create') }}">Ajouter le premier</a></span>@endcan</div>
    @endforelse
</div>
{{ $companies->links('partials.pagination') }}
@endsection
