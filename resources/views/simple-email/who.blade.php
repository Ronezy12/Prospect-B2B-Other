@extends('layouts.app')
@section('title', 'Écrire à plusieurs entreprises')

@section('content')
<div class="page-header">
    <div><h1>Écrire à plusieurs entreprises</h1><p class="subtitle">3 étapes, rien ne part avant votre validation.</p></div>
</div>
@include('simple-email.steps', ['current' => 1])
@error('who')<div class="alert alert-danger"><x-icon name="circle-alert" /><span>{{ $message }}</span></div>@enderror

<div class="grid grid-sidebar">
    <form method="GET" action="{{ route('pm.simple-email.create') }}" class="card wizard-card">
        <input type="hidden" name="vu" value="1">
        <h2>À quelles entreprises voulez-vous écrire ?</h2>
        <p class="muted small">Laissez vide pour écrire à tous vos prospects. Cliquez sur « Voir » pour mettre le nombre à jour.</p>
        <div class="form-grid">
            <div class="field">
                <label for="sector_id">Secteur</label>
                <select id="sector_id" name="sector_id"><option value="">Tous les secteurs</option>@foreach ($sectors as $s)<option value="{{ $s->id }}" @selected(($filters['sector_id'] ?? '') == $s->id)>{{ $s->name }}</option>@endforeach</select>
            </div>
            <div class="field">
                <label for="city">Ville</label>
                <input type="text" id="city" name="city" value="{{ $filters['city'] ?? '' }}" maxlength="120" placeholder="Toutes les villes">
            </div>
            <div class="field">
                <label for="status">Où en sont-ils ?</label>
                <select id="status" name="status"><option value="">Tous</option>@foreach ($statuses as $k => $l)<option value="{{ $k }}" @selected(($filters['status'] ?? '') === $k)>{{ $l }}</option>@endforeach</select>
            </div>
        </div>
        <label class="checkbox"><input type="checkbox" name="include_recent" value="1" @checked(! empty($filters['include_recent']))> Inclure aussi les personnes déjà contactées ces 30 derniers jours</label>
        <div class="form-actions"><button type="submit" class="btn"><x-icon name="search" /> Voir</button></div>
    </form>

    <aside class="card wizard-count">
        <div class="count-big">{{ $estimate['eligible'] }}</div>
        <div class="count-label">personne(s) recevront l'e-mail</div>
        @if ($sample['names']->isNotEmpty())
            <p class="small muted">Par exemple : {{ $sample['names']->implode(', ') }}{{ $sample['count'] > 6 ? '…' : '' }}</p>
        @endif
        @include('simple-email.removed')
        <form method="POST" action="{{ route('pm.simple-email.who') }}">
            @csrf
            @foreach ($filters as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
            <button type="submit" class="btn btn-primary btn-block" @disabled($estimate['eligible'] === 0)>Continuer <x-icon name="arrow-right" /></button>
        </form>
    </aside>
</div>
@endsection
