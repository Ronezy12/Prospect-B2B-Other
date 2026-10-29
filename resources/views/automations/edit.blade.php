@extends('layouts.app')
@section('title', 'Constructeur — '.$automation->name)

@push('head')
    <script src="{{ asset('js/automation-builder.js') }}?v=1" defer></script>
@endpush

@section('content')
<div class="page-header">
    <div>
        <div class="breadcrumb"><a href="{{ route('pm.automations.index') }}">Automatisations</a> <x-icon name="chevron-right" /> <a href="{{ route('pm.automations.show', $automation) }}">{{ $automation->name }}</a></div>
        <h1>Constructeur de scénario</h1>
        <p class="subtitle">Version actuelle : v{{ $automation->current_version }} · enregistrer crée une nouvelle version.</p>
    </div>
</div>

<form method="POST" action="{{ route('pm.automations.update', $automation) }}" data-builder>
    @csrf @method('PUT')
    <section class="card">
        <div class="form-grid">
            <div class="field full"><label for="name">Nom *</label><input type="text" id="name" name="name" value="{{ old('name', $automation->name) }}" required maxlength="150"></div>
            <div class="field">
                <label for="trigger">Quand… (déclencheur) *</label>
                <select id="trigger" name="trigger" required>
                    @foreach (config('prospection.automation.triggers') as $k => $l)<option value="{{ $k }}" @selected(old('trigger', $automation->trigger) === $k)>{{ $l }}</option>@endforeach
                </select>
                @error('trigger')<div class="error">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label>Au maximum, pour une même entreprise</label>
                <div class="toolbar">
                    <input type="number" name="max_runs_per_subject" value="{{ old('max_runs_per_subject', $automation->max_runs_per_subject) }}" min="1" max="50" aria-label="Nombre maximal" required>
                    <span class="small muted">fois au plus sur</span>
                    <input type="number" name="frequency_days" value="{{ old('frequency_days', $automation->frequency_days) }}" min="1" max="365" aria-label="Période en jours" required>
                    <span class="small muted">jours</span>
                </div>
            </div>
        </div>
    </section>

    @error('steps')<div class="alert alert-danger"><x-icon name="circle-alert" /><span>{{ $message }}</span></div>@enderror

    <noscript><div class="alert alert-warning">Le constructeur nécessite JavaScript.</div></noscript>
    <div class="steps-list" data-steps>
        @foreach ($steps as $i => $step)
            @include('automations.step', ['i' => $i, 'step' => $step])
        @endforeach
    </div>
    <template data-step-template>@include('automations.step', ['i' => '__INDEX__', 'step' => []])</template>

    <div class="toolbar section-gap">
        <button type="button" class="btn" data-add-step="action"><x-icon name="plus" /> Action</button>
        <button type="button" class="btn" data-add-step="delay"><x-icon name="clock" /> Attente</button>
        <button type="button" class="btn" data-add-step="condition"><x-icon name="arrow-right-left" /> Condition</button>
        <span class="grow"></span>
        <a class="btn btn-ghost" href="{{ route('pm.automations.show', $automation) }}">Annuler</a>
        <button type="submit" class="btn btn-primary"><x-icon name="check" /> Enregistrer la version</button>
    </div>
    <p class="small muted">Les étapes s'exécutent dans l'ordre. Une condition oriente vers une autre étape ou vers la fin. Les actions respectent toujours la liste d'opposition et les règles de consentement.</p>
</form>
@endsection
