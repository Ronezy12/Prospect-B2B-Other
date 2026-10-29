@extends('layouts.app')
@section('title', 'Conversation')

@section('content')
<div class="page-header">
    <div>
        <div class="breadcrumb"><a href="{{ route('pm.social.inbox.index') }}">Messages de la Page</a></div>
        <h1>{{ $conversation->participant_label ?: 'Correspondant '.\Illuminate\Support\Str::mask($conversation->participant_id, '•', 0, -4) }}</h1>
        <p class="subtitle"><span class="badge plain">{{ $conversation->channelLabel() }}</span> {{ $conversation->account?->name }}</p>
    </div>
    <div class="actions">
        <form method="POST" action="{{ route('pm.social.inbox.archive', $conversation) }}">
            @csrf
            <input type="hidden" name="status" value="{{ $conversation->status === 'archivee' ? 'ouverte' : 'archivee' }}">
            <button type="submit" class="btn">{{ $conversation->status === 'archivee' ? 'Rouvrir' : 'Archiver' }}</button>
        </form>
    </div>
</div>

<div class="grid grid-sidebar">
    <div class="stack">
        <section class="card">
            <h2><x-icon name="messages-square" /> Échanges</h2>
            <ul class="chat">
                @foreach ($conversation->messages as $message)
                    <li @class(['chat-msg', 'out' => $message->direction === 'sortant'])>
                        <div class="bubble pre">{{ $message->body ?? '—' }}</div>
                        <div class="meta">
                            {{ $message->sent_at->format('d/m/Y H:i') }}
                            @if ($message->direction === 'sortant')
                                · {{ $message->user?->name ?? 'Depuis les outils Meta' }} ·
                                @switch($message->status)
                                    @case('accepte') Accepté par Meta @break
                                    @case('echec') <span class="danger">Échec : {{ $message->error }}</span> @break
                                    @default Envoyé hors du module
                                @endswitch
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>

            @if ($conversation->canReply() && ! $conversation->profile?->isOpposed())
                <form method="POST" action="{{ route('pm.social.inbox.reply', $conversation) }}">
                    @csrf
                    <div class="field">
                        <label for="body">Votre réponse</label>
                        <textarea id="body" name="body" required maxlength="2000" rows="4">{{ old('body') }}</textarea>
                        <div class="hint">Réponse possible jusqu'au {{ $conversation->replyDeadline()->format('d/m/Y à H:i') }} (fenêtre de {{ config('prospection.social.meta.reply_window_hours') }} h après le dernier message de la personne).</div>
                        @error('body')<div class="error">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-actions"><button type="submit" class="btn btn-primary"><x-icon name="send" /> Envoyer via Meta</button></div>
                </form>
            @else
                <div class="alert alert-info"><x-icon name="clock" /><span>
                    @if ($conversation->profile?->isOpposed()) Ce prospect a demandé à ne plus être contacté sur ce réseau.
                    @else La fenêtre de réponse est fermée : Meta n'autorise un message que dans les {{ config('prospection.social.meta.reply_window_hours') }} h suivant le dernier message de la personne. Elle pourra être recontactée si elle écrit de nouveau à la Page.
                    @endif
                </span></div>
                @error('body')<div class="alert alert-danger"><x-icon name="circle-alert" /><span>{{ $message }}</span></div>@enderror
            @endif
        </section>
    </div>

    <div class="stack">
        <section class="card">
            <h2>Rattachement</h2>
            <p class="small muted">Rattachez la conversation à un prospect pour que les réponses alimentent son historique et ses scénarios. Le rapprochement est fait par vous : aucun croisement automatique d'identité.</p>
            <form method="POST" action="{{ route('pm.social.inbox.link', $conversation) }}">
                @csrf @method('PUT')
                <div class="field"><label for="participant_label">Nom affiché</label><input type="text" id="participant_label" name="participant_label" value="{{ old('participant_label', $conversation->participant_label) }}" maxlength="150" placeholder="ex. Claire — Agence du Parc"></div>
                <div class="field">
                    <label for="company_id">Prospect</label>
                    <select id="company_id" name="company_id">
                        <option value="">— Non rattachée —</option>
                        @foreach ($companies as $company)<option value="{{ $company->id }}" @selected($conversation->company_id === $company->id)>{{ $company->name }}</option>@endforeach
                    </select>
                </div>
                @if ($profiles->isNotEmpty())
                    <div class="field">
                        <label for="profile_id">Profil suivi</label>
                        <select id="profile_id" name="profile_id">
                            <option value="">— Aucun —</option>
                            @foreach ($profiles as $p)<option value="{{ $p->id }}" @selected($conversation->profile_id === $p->id)>{{ $p->platformLabel() }} · {{ $p->url }}</option>@endforeach
                        </select>
                    </div>
                @endif
                <div class="form-actions"><button type="submit" class="btn"><x-icon name="link-2" /> Enregistrer</button></div>
            </form>
            @if ($conversation->company)
                <p class="small"><a href="{{ route('pm.companies.show', $conversation->company) }}">Voir la fiche {{ $conversation->company->name }}</a></p>
            @endif
        </section>
    </div>
</div>
@endsection
