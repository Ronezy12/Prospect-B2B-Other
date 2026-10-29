@php($showContext = $showContext ?? true)
<div @class(['task-row', 'done' => ! $task->isOpen()])>
    @can('update', $task)
        <form method="POST" action="{{ route('pm.tasks.complete', $task) }}">
            @csrf
            <input type="hidden" name="status" value="{{ $task->isOpen() ? 'fait' : 'a_faire' }}">
            <button type="submit" @class(['check-btn', 'on' => $task->status === 'fait']) title="{{ $task->isOpen() ? 'Marquer comme faite' : 'Rouvrir la tâche' }}" aria-label="{{ $task->isOpen() ? 'Marquer comme faite' : 'Rouvrir la tâche' }}"><x-icon name="check" /></button>
        </form>
    @endcan
    <div class="grow">
        <div class="t-title">{{ $task->title }}</div>
        <div class="t-meta">
            <span @class(['late' => $task->isOverdue()])><x-icon name="calendar-clock" /> {{ $task->due_at->format('d/m/Y à H:i') }}@if ($task->isOverdue()) · en retard @endif</span>
            <span>{{ config('prospection.task_types')[$task->type] ?? $task->type }}</span>
            @if ($task->priority !== 'normale')<span>@include('partials.priority', ['priority' => $task->priority])</span>@endif
            @if ($task->status === 'annule')<span class="badge plain">Annulée</span>@endif
            @if ($showContext && $task->company)<span><x-icon name="building-2" /> <a href="{{ route('pm.companies.show', $task->company_id) }}">{{ $task->company->name }}</a></span>@endif
            @if ($showContext && $task->opportunity)<span><x-icon name="briefcase" /> <a href="{{ route('pm.opportunities.show', $task->opportunity_id) }}">{{ $task->opportunity->title }}</a></span>@endif
            @if ($task->assignee)<span><x-avatar :user="$task->assignee" size="sm" /> {{ $task->assignee->name }}</span>@endif
        </div>
        @if ($task->description)<div class="small pre">{{ $task->description }}</div>@endif
    </div>
    @can('update', $task)
        <a class="btn btn-sm btn-ghost" href="{{ route('pm.tasks.edit', $task) }}" aria-label="Modifier la tâche"><x-icon name="pencil" /></a>
    @endcan
</div>
