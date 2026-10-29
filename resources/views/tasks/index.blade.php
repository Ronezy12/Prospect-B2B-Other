@extends('layouts.app')
@section('title', 'Tâches et relances')

@section('content')
<div class="page-header">
    <div>
        <h1>Tâches et relances</h1>
        <p class="subtitle">{{ $assignee === 'me' ? 'Vos tâches' : 'Tâches de l\'équipe' }} · {{ $counts['retard'] }} en retard, {{ $counts['aujourdhui'] }} aujourd'hui</p>
    </div>
    <div class="actions">
        @can('create', \App\Models\Task::class)
            <a class="btn btn-primary" href="{{ route('pm.tasks.create') }}"><x-icon name="plus" /> Nouvelle tâche</a>
        @endcan
    </div>
</div>

<nav class="tabs" aria-label="Échéances">
    @foreach (\App\Http\Controllers\TaskController::VIEWS as $key => $label)
        <a href="{{ route('pm.tasks.index', ['vue' => $key] + ($assignee !== 'me' ? ['assignee_id' => $assignee] : [])) }}" @class(['tab', 'active' => $view === $key])>
            {{ $label }} @isset($counts[$key])<span class="count">{{ $counts[$key] }}</span>@endisset
        </a>
    @endforeach
</nav>

@if ($users->isNotEmpty())
    <form method="GET" class="toolbar">
        <input type="hidden" name="vue" value="{{ $view }}">
        <label class="sr-only" for="assignee_id">Responsable</label>
        <select id="assignee_id" name="assignee_id">
            <option value="me" @selected($assignee === 'me')>Mes tâches</option>
            <option value="all" @selected($assignee === 'all')>Toute l'équipe</option>
            @foreach ($users as $user)<option value="{{ $user->id }}" @selected((string) $assignee === (string) $user->id)>{{ $user->name }}</option>@endforeach
        </select>
        <button type="submit" class="btn"><x-icon name="filter" /> Afficher</button>
    </form>
@endif

<div class="card flush">
    @forelse ($tasks as $task)
        @include('tasks.row', ['task' => $task])
    @empty
        <div class="empty-state"><x-icon name="circle-check" /><strong>Rien à faire ici</strong>
            @if ($view === 'retard') Aucune tâche en retard. @elseif ($view === 'terminees') Aucune tâche terminée. @else Aucune tâche prévue sur cette période. @endif
        </div>
    @endforelse
    {{ $tasks->links('partials.pagination') }}
</div>
@endsection
