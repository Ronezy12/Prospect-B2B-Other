@extends('layouts.app')
@section('title', $profile->company->name.' — '.$profile->platformLabel())

@section('content')
<div class="page-header">
    <div>
        <div class="breadcrumb"><a href="{{ route('pm.social.index') }}">Réseaux sociaux</a> <x-icon name="chevron-right" /> <a href="{{ route('pm.companies.show', $profile->company) }}">{{ $profile->company->name }}</a></div>
        <h1>{{ $profile->contact?->fullName() ?? $profile->company->name }}</h1>
        <p class="subtitle">@include('social.partials.platform', ['platform' => $profile->platform]) @include('social.partials.status', ['status' => $profile->status]) <span class="muted break">{{ $profile->url }}</span></p>
    </div>
    <div class="actions">
        @unless ($profile->isOpposed())
            <a class="btn btn-primary" href="{{ $profile->url }}" target="_blank" rel="noopener noreferrer nofollow"><x-icon name="external-link" /> Ouvrir sur {{ $profile->platformLabel() }}</a>
        @endunless
        <form method="POST" action="{{ route('pm.social.destroy', $profile) }}" data-confirm="Retirer ce profil du suivi ? L'historique de la fiche est conservé{{ $profile->isOpposed() ? ', ainsi que l\'opposition' : '' }}.">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-danger"><x-icon name="trash-2" /> Retirer</button>
        </form>
    </div>
</div>

@if ($profile->isOpposed())
    <div class="alert alert-danger"><x-icon name="ban" /><span>Cette personne a demandé à ne plus être contactée sur ce réseau. Aucune nouvelle sollicitation ne doit être faite ; l'opposition est conservée même si le profil est retiré ou réimporté.</span></div>
@endif

<div class="grid grid-sidebar">
    <div class="stack">
        <section class="card outcome-card" id="action">
            <p class="outcomes-q">Qu'avez-vous fait sur {{ $profile->platformLabel() }} ?</p>
            <form method="POST" action="{{ route('pm.social.quick', $profile) }}" class="outcome-buttons">
                @csrf
                @foreach (config('prospection.social.quick_actions') as $key => $a)
                    @continue($profile->isOpposed() && in_array($a['type'], config('prospection.social.outbound_types'), true))
                    <button type="submit" name="action" value="{{ $key }}" @class(['outcome', 'tone-good' => in_array($key, ['reponse', 'rdv'], true), 'tone-bad' => $key === 'non'])>{{ $a['label'] }}</button>
                @endforeach
            </form>
            <p class="small muted">Le rappel suivant est créé tout seul. Pour une date ou une note précise :</p>
            <details class="more-options">
            <summary>Plus d'options</summary>
            <form method="POST" action="{{ route('pm.social.interaction', $profile) }}">
                @csrf
                <div class="form-grid">
                    <div class="field">
                        <label for="type">Action *</label>
                        <select id="type" name="type">
                            @foreach (config('prospection.social.interaction_types') as $key => $label)
                                @continue($profile->isOpposed() && in_array($key, config('prospection.social.outbound_types'), true))
                                <option value="{{ $key }}" @selected(old('type') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('type')<div class="error">{{ $message }}</div>@enderror
                    </div>
                    <div class="field">
                        <label for="occurred_at">Date</label>
                        <input type="datetime-local" id="occurred_at" name="occurred_at" value="{{ old('occurred_at', now()->format('Y-m-d\TH:i')) }}" max="{{ now()->format('Y-m-d\TH:i') }}">
                    </div>
                    @unless ($profile->isOpposed())
                        <div class="field">
                            <label for="status">Nouveau statut</label>
                            <select id="status" name="status">
                                <option value="">Automatique selon l'action</option>
                                @foreach (config('prospection.social.statuses') as $key => $label)
                                    @continue($key === 'ne_plus_contacter')
                                    <option value="{{ $key }}" @selected(old('status') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label for="next_action_at">Prochaine action le</label>
                            <input type="date" id="next_action_at" name="next_action_at" value="{{ old('next_action_at') }}" min="{{ now()->toDateString() }}">
                            @error('next_action_at')<div class="error">{{ $message }}</div>@enderror
                        </div>
                    @endunless
                    <div class="field full">
                        <label for="body">Compte rendu / message</label>
                        <textarea id="body" name="body" maxlength="5000" placeholder="Ce qui a été envoyé ou répondu, en quelques mots.">{{ old('body') }}</textarea>
                    </div>
                    @unless ($profile->isOpposed())
                        <div class="field full"><label class="checkbox"><input type="checkbox" name="create_task" value="1" @checked(old('create_task'))> Créer une tâche de relance à la date de la prochaine action</label></div>
                    @endunless
                </div>
                <div class="form-actions"><button type="submit" class="btn btn-primary"><x-icon name="check" /> Enregistrer l'action</button></div>
            </form>
            </details>
        </section>

        @if ($templates->isNotEmpty())
            <section class="card">
                <h2><x-icon name="copy" /> Messages prêts à copier</h2>
                <p class="small muted">Variables remplacées avec les informations de la fiche. Relisez et personnalisez avant de coller le texte sur la plateforme.</p>
                @foreach ($templates as $i => $item)
                    <div class="copy-block">
                        <div class="copy-head">
                            <strong>{{ $item['template']->name }}</strong>
                            <span class="badge plain">{{ $item['template']->kindLabel() }}</span>
                            <span class="small muted" data-count-for="tpl-{{ $i }}" @if ($item['template']->lengthLimit()) data-limit="{{ $item['template']->lengthLimit() }}" @endif>{{ mb_strlen($item['text']) }} caractères</span>
                            <button type="button" class="btn btn-sm" data-copy="tpl-{{ $i }}"><x-icon name="copy" /> Copier</button>
                        </div>
                        <textarea id="tpl-{{ $i }}" class="copy-text" rows="5" aria-label="Texte du modèle {{ $item['template']->name }}">{{ $item['text'] }}</textarea>
                        @if ($item['template']->lengthLimit() && mb_strlen($item['text']) > $item['template']->lengthLimit())
                            <div class="error">Dépasse la limite indicative de {{ $item['template']->lengthLimit() }} caractères pour une note d'invitation.</div>
                        @endif
                    </div>
                @endforeach
            </section>
        @endif

        <section class="card" id="historique">
            <h2><x-icon name="history" /> Actions sur ce profil</h2>
            <ul class="timeline">
                @forelse ($profile->interactions as $interaction)
                    <li>
                        <div class="meta">{{ $interaction->occurred_at->format('d/m/Y H:i') }} · {{ $interaction->typeLabel() }} · {{ $interaction->user?->name ?? ($interaction->origin === 'meta' ? 'Reçu via Meta' : 'Système') }}</div>
                        @if ($interaction->body)<div class="body pre">{{ $interaction->body }}</div>@endif
                    </li>
                @empty
                    <li class="muted">Aucune action enregistrée pour l'instant.</li>
                @endforelse
            </ul>
        </section>
    </div>

    <div class="stack">
        <section class="card">
            <h2>Suivi</h2>
            @if ($profile->isOpposed())
                <dl class="details">
                    <dt>Statut</dt><dd>@include('social.partials.status', ['status' => $profile->status])</dd>
                    <dt>Interlocuteur</dt><dd>{{ $profile->contact?->fullName() ?? '—' }}</dd>
                </dl>
            @else
                <form method="POST" action="{{ route('pm.social.update', $profile) }}">
                    @csrf @method('PUT')
                    <div class="field">
                        <label for="s-status">Statut</label>
                        <select id="s-status" name="status">
                            @foreach (config('prospection.social.statuses') as $key => $label)
                                @continue($key === 'ne_plus_contacter')
                                <option value="{{ $key }}" @selected($profile->status === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('status')<div class="error">{{ $message }}</div>@enderror
                    </div>
                    <div class="field">
                        <label for="s-next">Prochaine action</label>
                        <input type="date" id="s-next" name="next_action_at" value="{{ $profile->next_action_at?->toDateString() }}">
                    </div>
                    <div class="field">
                        <label for="s-contact">Interlocuteur</label>
                        <select id="s-contact" name="contact_id">
                            <option value="">— Page ou profil de l'entreprise —</option>
                            @foreach ($profile->company->contacts as $contact)<option value="{{ $contact->id }}" @selected($profile->contact_id === $contact->id)>{{ $contact->fullName() }}</option>@endforeach
                        </select>
                    </div>
                    <div class="form-actions"><button type="submit" class="btn"><x-icon name="check" /> Mettre à jour</button></div>
                </form>
            @endif
        </section>

        <section class="card">
            <h2>Prospect</h2>
            <dl class="details">
                <dt>Entreprise</dt><dd><a href="{{ route('pm.companies.show', $profile->company) }}">{{ $profile->company->name }}</a></dd>
                <dt>Statut commercial</dt><dd>@include('partials.status-badge', ['status' => $profile->company->status])</dd>
                <dt>Responsable</dt><dd>{{ $profile->company->owner?->name ?? 'Non assigné' }}</dd>
                <dt>Dernière action</dt><dd>{{ $profile->last_interaction_at?->format('d/m/Y H:i') ?? '—' }}</dd>
            </dl>
        </section>

        @unless ($profile->isOpposed())
            <section class="card">
                <h2><x-icon name="ban" /> Opposition</h2>
                <p class="small muted">La personne a demandé à ne plus être sollicitée sur ce réseau ? Enregistrez-le : le profil sort de la file de travail, les relances sociales ouvertes sont annulées et il ne pourra plus être réintroduit par un import.</p>
                <form method="POST" action="{{ route('pm.social.oppose', $profile) }}" data-confirm="Enregistrer l'opposition ? Elle ne pourra être levée que par la conformité.">
                    @csrf
                    <div class="field"><label for="note">Précision (date, canal de la demande…)</label><input type="text" id="note" name="note" maxlength="255"></div>
                    <label class="checkbox"><input type="checkbox" name="confirm" value="1" required> La personne a demandé à ne plus être contactée</label>
                    <div class="form-actions"><button type="submit" class="btn btn-danger">Enregistrer l'opposition</button></div>
                </form>
            </section>
        @endunless
    </div>
</div>
@endsection
