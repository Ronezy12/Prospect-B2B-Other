@extends('layouts.app')
@section('title', $automation->name)

@section('content')
@php($steps = $automation->latestVersion?->steps() ?? [])
<div class="page-header">
    <div>
        <div class="breadcrumb"><a href="{{ route('pm.automations.index') }}">Automatisations</a></div>
        <h1>{{ $automation->name }}</h1>
        <p class="subtitle">
            <span @class(['badge', 'badge-success' => $automation->status === 'active', 'badge-warning' => $automation->status === 'en_pause', 'badge-danger' => $automation->status === 'arretee'])>{{ $automation->statusLabel() }}</span>
            {{ $automation->triggerLabel() }} · au plus {{ $automation->max_runs_per_subject }} fois par fiche sur {{ $automation->frequency_days }} jours
            @if ($automation->activeVersion) · v{{ $automation->activeVersion->version }} active @endif
            @if ($automation->activeVersion && $automation->current_version > $automation->activeVersion->version) · <strong>v{{ $automation->current_version }} non publiée</strong>@endif
        </p>
    </div>
    <div class="actions">
        <a class="btn" href="{{ route('pm.automations.edit', $automation) }}"><x-icon name="pencil" /> Modifier</a>
        @if (in_array($automation->status, ['brouillon', 'arretee']) || ($automation->activeVersion && $automation->current_version > $automation->activeVersion->version))
            <form method="POST" action="{{ route('pm.automations.activate', $automation) }}">@csrf<button type="submit" class="btn btn-primary"><x-icon name="circle-check" /> {{ $automation->status === 'active' ? 'Publier la dernière version' : 'Activer' }}</button></form>
        @endif
        @if ($automation->status === 'active')
            <form method="POST" action="{{ route('pm.automations.pause', $automation) }}">@csrf<button type="submit" class="btn"><x-icon name="clock" /> Pause</button></form>
        @elseif ($automation->status === 'en_pause')
            <form method="POST" action="{{ route('pm.automations.resume', $automation) }}">@csrf<button type="submit" class="btn btn-primary">Reprendre</button></form>
        @endif
        @if (in_array($automation->status, ['active', 'en_pause']))
            <form method="POST" action="{{ route('pm.automations.stop', $automation) }}" data-confirm="Arrêter le scénario et toutes ses exécutions en attente ?">@csrf<button type="submit" class="btn btn-danger"><x-icon name="x" /> Arrêter</button></form>
        @endif
    </div>
</div>
@error('automation')<div class="alert alert-danger"><x-icon name="circle-alert" /><span>{{ $message }}</span></div>@enderror

<div class="grid grid-sidebar">
    <div class="stack">
        <section class="card">
            <h2>Déroulé (v{{ $automation->current_version }})</h2>
            <ol class="flow">
                <li class="flow-trigger"><x-icon name="sparkles" /> Quand : {{ $automation->triggerLabel() }}</li>
                @foreach ($steps as $i => $s)
                    <li>
                        <span class="flow-num">{{ $i + 1 }}</span>
                        @if ($s['type'] === 'delay')
                            <x-icon name="clock" /> Attendre {{ $s['days'] ?? 0 }} j {{ ($s['hours'] ?? 0) ? ($s['hours'].' h') : '' }}
                        @elseif ($s['type'] === 'condition')
                            <x-icon name="arrow-right-left" /> Si « {{ config('prospection.automation.conditions')[$s['field']] ?? $s['field'] }} » {{ config('prospection.automation.operators')[$s['operator']] ?? '' }} {{ $s['value'] ?? '' }}
                            <span class="small muted">→ {{ ($s['then'] ?? 'end') === 'end' ? 'fin' : 'étape '.substr($s['then'], 1) }}, sinon → {{ ($s['else'] ?? 'end') === 'end' ? 'fin' : 'étape '.substr($s['else'], 1) }}</span>
                        @else
                            <x-icon name="circle-check" /> {{ config('prospection.automation.actions')[$s['action'] ?? ''] ?? '' }}@if (! empty($s['title'])) : « {{ $s['title'] }} »@endif @if (! empty($s['status'])) : {{ config('prospection.company_statuses')[$s['status']] ?? '' }}@endif @if (! empty($s['tag'])) : {{ $s['tag'] }}@endif
                        @endif
                    </li>
                @endforeach
            </ol>
        </section>

        <section class="card flush">
            <div class="card-head"><h2>Historique des déclenchements</h2></div>
            @forelse ($runs as $run)
                <details class="run" id="run-{{ $run->id }}" @if (request()->fullUrlIs('*#run-'.$run->id) || $loop->first && $run->is_test) open @endif>
                    <summary>
                        <span @class(['badge', 'badge-success' => $run->status === 'termine', 'badge-info' => $run->status === 'en_attente', 'badge-danger' => in_array($run->status, ['erreur', 'arrete'])])>{{ \App\Models\AutomationRun::STATUS_LABELS[$run->status] ?? $run->status }}</span>
                        @if ($run->is_test)<span class="badge badge-warning">Test</span>@endif
                        <span>{{ class_basename($run->subject_type) === 'Company' ? 'Entreprise' : (class_basename($run->subject_type) === 'Opportunity' ? 'Opportunité' : 'Fiche') }} #{{ $run->subject_id }}</span>
                        <span class="small muted">v{{ $run->version?->version }} · {{ $run->created_at->format('d/m/Y H:i') }}@if ($run->resume_at && $run->status === 'en_attente') · reprise le {{ $run->resume_at->format('d/m H:i') }}@endif</span>
                    </summary>
                    <ul class="timeline">
                        @foreach ($run->logs as $log)
                            <li><div class="meta">{{ $log->created_at->format('d/m H:i:s') }} · étape {{ $log->step_id ? substr($log->step_id, 1) : '—' }} · {{ $log->result }}</div><div class="body">{{ $log->message }}</div></li>
                        @endforeach
                        @if ($run->error)<li><div class="body">{{ $run->error }}</div></li>@endif
                    </ul>
                </details>
            @empty
                <div class="empty-state"><x-icon name="history" /><strong>Aucune exécution</strong>Testez le scénario sur une fiche ou activez-le.</div>
            @endforelse
            {{ $runs->links('partials.pagination') }}
        </section>
    </div>

    <div class="stack">
        <form method="POST" action="{{ route('pm.automations.test', $automation) }}" class="card">
            @csrf
            <h2><x-icon name="search" /> Mode test</h2>
            <p class="small muted">Déroule la dernière version sur une fiche réelle : conditions évaluées, actions seulement décrites, délais ignorés. Rien n'est modifié.</p>
            <div class="field"><label for="company_id">Fiche entreprise</label><select id="company_id" name="company_id">@foreach ($companies as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
            <div class="form-actions"><button type="submit" class="btn"><x-icon name="search" /> Lancer le test</button></div>
        </form>
        <section class="card">
            <h2><x-icon name="history" /> Versions</h2>
            <ul class="timeline">
                @foreach ($versions as $v)
                    <li><div class="meta">v{{ $v->version }} · {{ $v->created_at->format('d/m/Y H:i') }}</div><div class="body">{{ count($v->steps()) }} étape(s)@if ($automation->active_version_id === $v->id) · <strong>active</strong>@endif</div></li>
                @endforeach
            </ul>
        </section>
        <form method="POST" action="{{ route('pm.automations.destroy', $automation) }}" data-confirm="Supprimer ce scénario ? Les exécutions en attente seront arrêtées.">@csrf @method('DELETE')<button type="submit" class="btn btn-ghost small"><x-icon name="trash-2" /> Supprimer le scénario</button></form>
    </div>
</div>
@endsection
