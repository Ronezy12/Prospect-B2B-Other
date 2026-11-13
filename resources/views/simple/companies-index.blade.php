@extends('layouts.app')
@section('title', 'Mes prospects')

@section('content')
<div class="s-head">
    <h1>Mes prospects</h1>
    @can('prospects.create')<a class="s-add" href="{{ route('pm.companies.create') }}">➕ Ajouter</a>@endcan
</div>

<form method="GET" class="s-search">
    <label class="sr-only" for="q">Rechercher</label>
    <input type="search" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="🔍 Chercher un nom, une ville…">
    <button type="submit" class="btn btn-primary">Chercher</button>
</form>

@forelse ($companies as $company)
    @php($contact = $company->contacts->first())
    @php($phone = $contact?->mobile ?? $contact?->phone ?? $company->phone)
    <a class="s-row" href="{{ route('pm.companies.show', $company) }}">
        <span class="s-row-main">
            <strong>{{ $company->name }}</strong>
            <span class="s-sub">{{ $contact?->fullName() ?? '—' }}@if ($company->city) · {{ $company->city }}@endif</span>
        </span>
        <span class="s-chip s-chip-{{ $company->status }}">{{ $company->statusLabel() }}</span>
        @if ($phone)<span class="s-row-phone">📞</span>@endif
        <span class="s-row-go">›</span>
    </a>
@empty
    <div class="s-empty">Aucun prospect trouvé.@can('prospects.create') <a href="{{ route('pm.companies.create') }}">Ajouter le premier</a>.@endcan</div>
@endforelse
{{ $companies->links('partials.pagination') }}
@endsection
