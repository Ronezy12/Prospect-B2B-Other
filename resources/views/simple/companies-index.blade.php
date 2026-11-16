@extends('layouts.app')
@section('title', 'Mes prospects')

@section('content')
<div class="s-head">
    <h1>Mes prospects</h1>
    @can('prospects.create')<a class="btn btn-primary" href="{{ route('pm.companies.create') }}"><x-icon name="plus" /> Ajouter un prospect</a>@endcan
</div>

<form method="GET" class="s-search">
    <label class="sr-only" for="q">Rechercher</label>
    <span class="s-search-field"><x-icon name="search" /><input type="search" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Nom, ville, e-mail…"></span>
    <button type="submit" class="btn btn-primary">Chercher</button>
</form>

@forelse ($companies as $company)
    @php($contact = $company->contacts->first())
    @php($phone = $contact?->mobile ?? $contact?->phone ?? $company->phone)
    <a class="s-row" href="{{ route('pm.companies.show', $company) }}">
        <x-avatar :name="$company->name" />
        <span class="s-row-main">
            <strong>{{ $company->name }}</strong>
            <span class="s-sub">{{ $contact?->fullName() ?? '—' }}@if ($company->city) · {{ $company->city }}@endif</span>
        </span>
        <span class="s-chip s-chip-{{ $company->status }}">{{ $company->statusLabel() }}</span>
        @if ($phone)<span class="s-row-phone">{{ \App\Support\Normalizer::displayPhone($phone) }}</span>@endif
        <x-icon name="chevron-right" class="s-row-go" />
    </a>
@empty
    <div class="s-empty">Aucun prospect trouvé.@can('prospects.create') <a href="{{ route('pm.companies.create') }}">Ajouter le premier</a>.@endcan</div>
@endforelse
{{ $companies->links('partials.pagination') }}
@endsection
