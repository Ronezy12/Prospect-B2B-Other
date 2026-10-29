@extends('layouts.app')
@section('title', $task->exists ? 'Modifier la tâche' : 'Nouvelle tâche')

@section('content')
<div class="page-header">
    <div>
        <div class="breadcrumb">
            <a href="{{ route('pm.tasks.index') }}">Tâches et relances</a>
            @if ($company) <x-icon name="chevron-right" /> <a href="{{ route('pm.companies.show', $company) }}">{{ $company->name }}</a>@endif
            @if ($opportunity) <x-icon name="chevron-right" /> <a href="{{ route('pm.opportunities.show', $opportunity) }}">{{ $opportunity->title }}</a>@endif
        </div>
        <h1>{{ $task->exists ? 'Modifier la tâche' : 'Nouvelle tâche' }}</h1>
    </div>
</div>

<form method="POST" action="{{ $task->exists ? route('pm.tasks.update', $task) : route('pm.tasks.store') }}" class="card">
    @csrf
    @if ($task->exists) @method('PUT') @endif
    @unless ($task->exists)
        @if ($company)<input type="hidden" name="company_id" value="{{ $company->id }}">@endif
        @if ($opportunity)<input type="hidden" name="opportunity_id" value="{{ $opportunity->id }}">@endif
    @endunless

    <div class="form-grid">
        <div class="field full">
            <label for="title">Intitulé *</label>
            <input type="text" id="title" name="title" value="{{ old('title', $task->title) }}" required maxlength="190">
            @error('title')<div class="error">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label for="type">Type *</label>
            <select id="type" name="type" required>
                @foreach (config('prospection.task_types') as $key => $label)<option value="{{ $key }}" @selected(old('type', $task->type) === $key)>{{ $label }}</option>@endforeach
            </select>
        </div>
        <div class="field">
            <label for="due_at">Échéance *</label>
            <input type="datetime-local" id="due_at" name="due_at" value="{{ old('due_at', $task->due_at?->format('Y-m-d\TH:i')) }}" required>
            @error('due_at')<div class="error">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label for="priority">Priorité *</label>
            <select id="priority" name="priority" required>
                @foreach (config('prospection.priorities') as $key => $label)<option value="{{ $key }}" @selected(old('priority', $task->priority) === $key)>{{ $label }}</option>@endforeach
            </select>
        </div>
        <div class="field">
            <label for="assignee_id">Responsable</label>
            <select id="assignee_id" name="assignee_id" @disabled($users->count() === 1)>
                @foreach ($users as $user)<option value="{{ $user->id }}" @selected(old('assignee_id', $task->assignee_id ?? auth()->id()) == $user->id)>{{ $user->name }}</option>@endforeach
            </select>
        </div>
        <div class="field full">
            <label for="description">Détails</label>
            <textarea id="description" name="description" maxlength="5000">{{ old('description', $task->description) }}</textarea>
        </div>
    </div>
    <div class="form-actions">
        @if ($task->exists)
            @can('update', $task)
                @if ($task->isOpen())
                    <button type="submit" form="cancel-task" class="btn btn-ghost">Annuler la tâche</button>
                @endif
            @endcan
        @endif
        <a class="btn" href="{{ url()->previous() }}">Retour</a>
        <button type="submit" class="btn btn-primary"><x-icon name="check" /> Enregistrer</button>
    </div>
</form>
@if ($task->exists && $task->isOpen())
    <form method="POST" action="{{ route('pm.tasks.complete', $task) }}" id="cancel-task">@csrf<input type="hidden" name="status" value="annule"></form>
@endif
@endsection
