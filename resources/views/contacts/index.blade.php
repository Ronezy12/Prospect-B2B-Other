@extends('layouts.app')
@section('title', 'Entreprises et contacts')

@section('content')
<div class="page-header">
    <div>
        <h1>Contacts</h1>
        <p class="subtitle">{{ number_format($contacts->total(), 0, ',', ' ') }} interlocuteur(s) dans les entreprises de votre périmètre</p>
    </div>
</div>

<form class="card" method="GET" action="{{ route('pm.contacts.index') }}" role="search">
    <div class="filters">
        <div class="field wide">
            <label for="q">Recherche</label>
            <div class="searchbox"><x-icon name="search" /><input type="search" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Nom, e-mail, fonction, entreprise…"></div>
        </div>
        <div class="field">
            <label for="sector_id">Secteur de l'entreprise</label>
            <select id="sector_id" name="sector_id">
                <option value="">Tous</option>
                @foreach ($sectors as $sector)
                    <option value="{{ $sector->id }}" @selected(($filters['sector_id'] ?? '') == $sector->id)>{{ $sector->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="audience_type">Type</label>
            <select id="audience_type" name="audience_type">
                <option value="">Tous</option>
                @foreach (config('prospection.audience_types') as $key => $label)
                    <option value="{{ $key }}" @selected(($filters['audience_type'] ?? '') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="legal_basis">Base légale</label>
            <select id="legal_basis" name="legal_basis">
                <option value="">Toutes</option>
                @foreach (config('prospection.legal_bases') as $key => $label)
                    <option value="{{ $key }}" @selected(($filters['legal_basis'] ?? '') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="field buttons">
            <button type="submit" class="btn btn-primary"><x-icon name="search" /> Filtrer</button>
            <a class="btn" href="{{ route('pm.contacts.index') }}">Réinitialiser</a>
        </div>
    </div>
</form>

<div class="card flush">
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr><th>Interlocuteur</th><th>Fonction</th><th>Entreprise</th><th>Coordonnées</th><th>Conformité</th><th>Responsable</th></tr>
            </thead>
            <tbody>
            @forelse ($contacts as $contact)
                <tr>
                    <td><span class="person"><x-avatar :name="$contact->fullName()" size="sm" /> <span class="row-title">{{ $contact->fullName() }}</span></span></td>
                    <td>{{ $contact->job_title ?? '—' }}</td>
                    <td>
                        @if ($contact->company)
                            <a href="{{ route('pm.companies.show', $contact->company) }}">{{ $contact->company->name }}</a>
                            <div class="sub">{{ $contact->company->city }}</div>
                        @else — @endif
                    </td>
                    <td>
                        @if ($contact->email)<div>{{ $contact->email }}</div>@endif
                        @if ($contact->phone ?? $contact->mobile)<div class="sub">{{ \App\Support\Normalizer::displayPhone($contact->phone ?? $contact->mobile) }}</div>@endif
                    </td>
                    <td>
                        <span class="badge plain">{{ strtoupper($contact->audience_type) }}</span>
                        @if ($contact->email && isset($suppressed[$contact->email]))<span class="badge badge-danger">Opposition</span>@endif
                    </td>
                    <td>{{ $contact->owner?->name ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="table-empty"><div class="empty-state"><x-icon name="contact-round" /><strong>Aucun contact trouvé</strong>Les interlocuteurs s'ajoutent depuis la fiche d'une entreprise.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $contacts->links('partials.pagination') }}
</div>
@endsection
