@extends('layouts.app')
@section('title', 'Notifications')

@section('content')
<div class="page-header">
    <div><h1>Notifications</h1><p class="subtitle">Messages des scénarios d'automatisation et alertes du système</p></div>
    <form method="POST" action="{{ route('pm.notifications.read_all') }}" class="actions">@csrf<button type="submit" class="btn"><x-icon name="check" /> Tout marquer comme lu</button></form>
</div>
<div class="card flush">
    @forelse ($notifications as $n)
        <a href="{{ route('pm.notifications.open', $n) }}" @class(['task-row', 'unread' => ! $n->read_at])>
            <span @class(['dot-state', 'on' => ! $n->read_at])></span>
            <div class="grow"><div class="t-title">{{ $n->title }}</div>@if ($n->body)<div class="small muted">{{ $n->body }}</div>@endif</div>
            <span class="small muted nowrap">{{ $n->created_at->format('d/m H:i') }}</span>
        </a>
    @empty
        <div class="empty-state"><x-icon name="alarm-clock" /><strong>Aucune notification</strong>Les scénarios « notifier le responsable » écrivent ici.</div>
    @endforelse
    {{ $notifications->links('partials.pagination') }}
</div>
@endsection
