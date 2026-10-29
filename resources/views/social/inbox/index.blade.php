@extends('layouts.app')
@section('title', 'Messages de la Page')

@section('content')
<div class="page-header">
    <div>
        <div class="breadcrumb"><a href="{{ route('pm.social.index') }}">Réseaux sociaux</a></div>
        <h1>Messages de la Page</h1>
        <p class="subtitle">Conversations ouvertes par des personnes ayant écrit à votre Page Facebook ou à votre compte Instagram professionnel (API officielle Meta).</p>
    </div>
</div>

@unless ($hasAccount)
    <div class="alert alert-info"><x-icon name="circle-alert" /><span>Aucune connexion Meta n'est configurée. @can('settings.manage')<a href="{{ route('pm.settings.social.create') }}">Configurer la connexion officielle</a>.@else Demandez à un administrateur de la configurer.@endcan</span></div>
@endunless

<nav class="tabs" aria-label="État">
    <a href="{{ route('pm.social.inbox.index') }}" @class(['tab', 'active' => $status === 'ouverte'])>Ouvertes</a>
    <a href="{{ route('pm.social.inbox.index', ['statut' => 'archivee']) }}" @class(['tab', 'active' => $status === 'archivee'])>Archivées</a>
</nav>

<div class="card flush">
    @forelse ($conversations as $conversation)
        <div class="task-row">
            <div class="grow">
                <a class="t-title" href="{{ route('pm.social.inbox.show', $conversation) }}">{{ $conversation->participant_label ?: 'Correspondant '.\Illuminate\Support\Str::mask($conversation->participant_id, '•', 0, -4) }}</a>
                <div class="t-meta">
                    <span class="badge plain">{{ $conversation->channelLabel() }}</span>
                    @if ($conversation->unread)<span class="badge badge-warning">Non lu</span>@endif
                    <span>{{ $conversation->company?->name ?? 'Non rattachée à un prospect' }}</span>
                    <span>{{ $conversation->messages_count }} message(s)</span>
                    @if ($conversation->last_message_at)<span>{{ $conversation->last_message_at->diffForHumans() }}</span>@endif
                    @if ($conversation->canReply())<span class="badge badge-success">Réponse possible jusqu'au {{ $conversation->replyDeadline()->format('d/m H:i') }}</span>@endif
                </div>
            </div>
        </div>
    @empty
        <div class="empty-state"><x-icon name="messages-square" /><strong>Aucune conversation</strong>Les messages reçus par la Page apparaîtront ici dès que le webhook Meta sera actif.</div>
    @endforelse
    {{ $conversations->links('partials.pagination') }}
</div>
@endsection
