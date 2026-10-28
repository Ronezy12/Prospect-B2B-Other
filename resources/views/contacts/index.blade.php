@extends('layouts.app')
@section('title', 'Entreprises et contacts')

@section('content')
<div class="page-header">
    <h1>Entreprises et contacts</h1>
</div>

<form class="card" method="GET" action="{{ route('pm.contacts.index') }}" role="search">
    <div class="filters">
        <div class="field">
            <label for="q">Recherche</label>
            <input type="search" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Nom, e-mail, fonction, entreprise…">
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
        <div class="field">
            <button type="submit" class="btn btn-primary">Filtrer</button>
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
                    <td>{{ $contact->fullName() }}</td>
                    <td>{{ $contact->job_title ?? '—' }}</td>
                    <td>
                        @if ($contact->company)
                            <a href="{{ route('pm.companies.show', $contact->company) }}">{{ $contact->company->name }}</a>
                            <div class="small muted">{{ $contact->company->city }}</div>
                        @else — @endif
                    </td>
                    <td>
                        @if ($contact->email)<div>{{ $contact->email }}</div>@endif
                        @if ($contact->phone ?? $contact->mobile)<div>{{ $contact->phone ?? $contact->mobile }}</div>@endif
                    </td>
                    <td>
                        <span class="badge">{{ strtoupper($contact->audience_type) }}</span>
                        @if ($contact->email && isset($suppressed[$contact->email]))<span class="badge badge-danger">Opposition</span>@endif
                    </td>
                    <td>{{ $contact->owner?->name ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="table-empty">Aucun contact ne correspond à ces critères.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $contacts->links('partials.pagination') }}
</div>
@endsection
