@extends('layouts.app')
@section('title', $opportunity->exists ? 'Modifier l\'opportunité' : 'Nouvelle opportunité')

@section('content')
<div class="page-header">
    <div>
        <div class="breadcrumb"><a href="{{ route('pm.companies.show', $company) }}">{{ $company->name }}</a> <x-icon name="chevron-right" /> {{ $opportunity->exists ? 'Modifier l\'opportunité' : 'Nouvelle opportunité' }}</div>
        <h1>{{ $opportunity->exists ? $opportunity->title : 'Nouvelle opportunité' }}</h1>
    </div>
</div>

<form method="POST" action="{{ $opportunity->exists ? route('pm.opportunities.update', $opportunity) : route('pm.opportunities.store') }}" class="card">
    @csrf
    @if ($opportunity->exists) @method('PUT') @else <input type="hidden" name="company_id" value="{{ $company->id }}"> @endif

    <fieldset>
        <legend><x-icon name="briefcase" /> Opportunité</legend>
        <div class="form-grid">
            <div class="field full">
                <label for="title">Intitulé *</label>
                <input type="text" id="title" name="title" value="{{ old('title', $opportunity->title) }}" required maxlength="190">
                @error('title')<div class="error">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="pipeline_id">Pipeline *</label>
                @if ($opportunity->exists)
                    <input type="text" id="pipeline_id" value="{{ $opportunity->pipeline->name }}" disabled>
                    <div class="hint">La pipeline d'une opportunité existante ne change pas.</div>
                @else
                    <select id="pipeline_id" name="pipeline_id" required data-pipeline-select>
                        @foreach ($pipelines as $p)<option value="{{ $p->id }}" @selected(old('pipeline_id', $defaultPipeline?->id) == $p->id)>{{ $p->name }}</option>@endforeach
                    </select>
                @endif
            </div>
            <div class="field">
                <label for="stage_id">Étape *</label>
                <select id="stage_id" name="stage_id" required data-stage-select>
                    @foreach ($opportunity->exists ? collect([$opportunity->pipeline]) : $pipelines as $p)
                        @foreach ($p->stages as $s)
                            <option value="{{ $s->id }}" data-pipeline="{{ $p->id }}" @selected(old('stage_id', $opportunity->stage_id) == $s->id)>{{ $s->name }}</option>
                        @endforeach
                    @endforeach
                </select>
                @error('stage_id')<div class="error">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="contact_id">Interlocuteur</label>
                <select id="contact_id" name="contact_id">
                    <option value="">—</option>
                    @foreach ($company->contacts as $c)<option value="{{ $c->id }}" @selected(old('contact_id', $opportunity->contact_id) == $c->id)>{{ $c->fullName() }}</option>@endforeach
                </select>
            </div>
            <div class="field">
                <label for="amount">Montant estimé (€ HT)</label>
                <input type="number" id="amount" name="amount" value="{{ old('amount', $opportunity->amount) }}" min="0" step="0.01">
                @error('amount')<div class="error">{{ $message }}</div>@enderror
            </div>
        </div>
    </fieldset>

    <fieldset>
        <legend><x-icon name="calendar-clock" /> Suivi</legend>
        <div class="form-grid">
            <div class="field">
                <label for="priority">Priorité *</label>
                <select id="priority" name="priority" required>
                    @foreach (config('prospection.priorities') as $key => $label)<option value="{{ $key }}" @selected(old('priority', $opportunity->priority) === $key)>{{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="field">
                <label for="owner_id">Responsable</label>
                @can('prospects.assign')
                    <select id="owner_id" name="owner_id">
                        <option value="">Non assignée</option>
                        @foreach ($users as $user)<option value="{{ $user->id }}" @selected(old('owner_id', $opportunity->owner_id ?? $company->owner_id) == $user->id)>{{ $user->name }}</option>@endforeach
                    </select>
                @else
                    <input type="text" id="owner_id" value="{{ $opportunity->owner?->name ?? $company->owner?->name ?? auth()->user()->name }}" disabled>
                @endcan
            </div>
            <div class="field">
                <label for="next_action">Prochaine action</label>
                <input type="text" id="next_action" name="next_action" value="{{ old('next_action', $opportunity->next_action) }}" maxlength="255" placeholder="ex. Envoyer la proposition révisée">
            </div>
            <div class="field">
                <label for="follow_up_at">Date de relance</label>
                <input type="date" id="follow_up_at" name="follow_up_at" value="{{ old('follow_up_at', $opportunity->follow_up_at?->format('Y-m-d')) }}">
            </div>
            <div class="field full">
                <label for="lost_reason">Motif de perte (si perdue)</label>
                <input type="text" id="lost_reason" name="lost_reason" value="{{ old('lost_reason', $opportunity->lost_reason) }}" maxlength="255">
            </div>
        </div>
    </fieldset>

    <div class="form-actions">
        <a class="btn" href="{{ $opportunity->exists ? route('pm.opportunities.show', $opportunity) : route('pm.companies.show', $company) }}">Annuler</a>
        <button type="submit" class="btn btn-primary"><x-icon name="check" /> Enregistrer</button>
    </div>
</form>
@endsection
