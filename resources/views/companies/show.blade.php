@extends('layouts.app')
@section('title', $company->name)

@section('content')
<div class="page-header">
    <div>
        <div class="breadcrumb"><a href="{{ route('pm.companies.index') }}">Base de prospects</a> <x-icon name="chevron-right" /> Fiche</div>
        <h1>{{ $company->name }}</h1>
        <p class="subtitle">
            @if ($company->isClient())<span class="badge badge-success">Client depuis le {{ $company->converted_at?->format('d/m/Y') }}</span>
            @else @include('partials.status-badge', ['status' => $company->status]) @endif
            {{ $company->sector?->name }}@if ($company->city) · {{ $company->city }}@endif
        </p>
    </div>
    <div class="actions">
        @can('update', $company)
            <a class="btn" href="{{ route('pm.companies.edit', $company) }}"><x-icon name="pencil" /> Modifier</a>
        @endcan
        @can('delete', $company)
            <form method="POST" action="{{ route('pm.companies.destroy', $company) }}" data-confirm="Supprimer cette fiche ? Elle sera archivée.">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-danger"><x-icon name="trash-2" /> Supprimer</button>
            </form>
        @endcan
    </div>
</div>

<div class="grid grid-sidebar">
    <div class="stack">
        <section class="card">
            <h2>Informations</h2>
            <dl class="details">
                <dt>SIREN / SIRET</dt><dd>{{ $company->registration_number ?? '—' }}</dd>
                <dt>Secteur</dt><dd>{{ $company->sector?->name ?? '—' }}@if ($company->subsector) › {{ $company->subsector->name }}@endif</dd>
                <dt>Site web</dt><dd>@if ($company->website)<a href="{{ $company->website }}" target="_blank" rel="noopener noreferrer nofollow">{{ $company->website }}</a>@else — @endif</dd>
                <dt>E-mail</dt><dd>{{ $company->email ?? '—' }}</dd>
                <dt>Téléphone</dt><dd>{{ \App\Support\Normalizer::displayPhone($company->phone) ?? '—' }}</dd>
                <dt>Adresse</dt><dd>{{ collect([$company->address, trim($company->postal_code.' '.$company->city), $company->country !== 'FR' ? $company->country : null])->filter()->implode(', ') ?: '—' }}</dd>
                <dt>Source</dt><dd>{{ $company->source?->name ?? '—' }}</dd>
                <dt>Acquis le</dt><dd>{{ $company->acquired_at?->format('d/m/Y') ?? '—' }}</dd>
                <dt>Responsable</dt><dd>@if ($company->owner)<span class="person"><x-avatar :user="$company->owner" size="sm" /> {{ $company->owner->name }}</span>@else Non assigné @endif</dd>
                <dt>Tags</dt><dd>@forelse ($company->tags as $tag)<span class="tag">{{ $tag->name }}</span>@empty — @endforelse</dd>
                @if ($company->notes)
                    <dt>Notes</dt><dd class="pre">{{ $company->notes }}</dd>
                @endif
            </dl>
        </section>

        <section class="card flush">
            <div class="card-head">
                <h2>Interlocuteurs <span class="muted">· {{ $company->contacts->count() }}</span></h2>
                @can('update', $company)
                    <a class="btn btn-sm" href="{{ route('pm.contacts.create', ['company_id' => $company->id]) }}"><x-icon name="user-plus" /> Ajouter</a>
                @endcan
            </div>
            @forelse ($company->contacts as $contact)
                <div class="contact-card">
                    <x-avatar :name="$contact->fullName()" />
                    <div class="grow">
                        <div class="name">{{ trim($contact->civility.' '.$contact->fullName()) }}
                            @if ($contact->is_primary)<span class="badge badge-primary plain">Principal</span>@endif
                        </div>
                        <div class="small muted">{{ $contact->job_title ?? 'Fonction non renseignée' }}</div>
                        <div class="lines">
                            @if ($contact->email)<span><x-icon name="mail" /> {{ $contact->email }}</span>@endif
                            @if ($contact->phone)<span><x-icon name="phone" /> {{ \App\Support\Normalizer::displayPhone($contact->phone) }}</span>@endif
                            @if ($contact->mobile)<span><x-icon name="phone" /> {{ \App\Support\Normalizer::displayPhone($contact->mobile) }}</span>@endif
                            @if ($contact->linkedin_url)<span><x-icon name="user-round" /> <a href="{{ $contact->linkedin_url }}" target="_blank" rel="noopener noreferrer nofollow">LinkedIn</a></span>@endif
                        </div>
                        <div class="lines">
                            <span class="badge plain">{{ strtoupper($contact->audience_type) }} · {{ config('prospection.legal_bases')[$contact->legal_basis] ?? $contact->legal_basis }}</span>
                            @if ($contact->email && isset($suppressed[$contact->email]))
                                <span class="badge badge-danger">Opposition enregistrée</span>
                            @endif
                            @if ($contact->audience_type === 'b2c' && $contact->legal_basis !== 'consentement')
                                <span class="badge badge-warning">Consentement requis</span>
                            @endif
                        </div>
                        @can('update', $contact)
                            @if ($contact->email && ! isset($suppressed[$contact->email]))
                                <details class="inline-form">
                                    <summary>Enregistrer une opposition</summary>
                                    <form method="POST" action="{{ route('pm.contacts.suppress', $contact) }}" data-confirm="Confirmer l'opposition ? Elle ne pourra être levée que par la conformité.">
                                        @csrf
                                        <div class="field">
                                            <label for="scope-{{ $contact->id }}">Périmètre</label>
                                            <select id="scope-{{ $contact->id }}" name="scope">
                                                @foreach (config('prospection.suppression_scopes') as $key => $label)
                                                    <option value="{{ $key }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="field">
                                            <label for="note-{{ $contact->id }}">Précision (canal, date de la demande…)</label>
                                            <input type="text" id="note-{{ $contact->id }}" name="note" maxlength="255">
                                        </div>
                                        <button type="submit" class="btn btn-sm btn-danger">Enregistrer l'opposition</button>
                                    </form>
                                </details>
                            @endif
                        @endcan
                    </div>
                    <div class="actions">
                        @can('update', $contact)
                            <a class="btn btn-sm" href="{{ route('pm.contacts.edit', $contact) }}"><x-icon name="pencil" /> Modifier</a>
                        @endcan
                        @can('delete', $contact)
                            <form method="POST" action="{{ route('pm.contacts.destroy', $contact) }}" data-confirm="Supprimer ce contact ?">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-link danger small">Supprimer</button>
                            </form>
                        @endcan
                    </div>
                </div>
            @empty
                <div class="empty-state"><x-icon name="contact-round" /><strong>Aucun interlocuteur</strong>Ajoutez la personne à contacter dans cette entreprise.</div>
            @endforelse
        </section>

        <section class="card" id="historique">
            <h2><x-icon name="history" /> Historique</h2>
            @can('update', $company)
                <form method="POST" action="{{ route('pm.companies.activities.store', $company) }}">
                    @csrf
                    <div class="form-grid">
                        <div class="field">
                            <label for="type">Type</label>
                            <select id="type" name="type">
                                @foreach (config('prospection.activity_types') as $key => $label)
                                    @continue($key === 'systeme')
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field full">
                            <label for="body">Compte rendu</label>
                            <textarea id="body" name="body" required maxlength="5000"></textarea>
                        </div>
                    </div>
                    <div class="form-actions"><button type="submit" class="btn btn-primary"><x-icon name="plus" /> Ajouter à l'historique</button></div>
                </form>
            @endcan
            <ul class="timeline">
                @forelse ($activities as $activity)
                    <li>
                        <div class="meta">{{ $activity->occurred_at->format('d/m/Y H:i') }} · {{ config('prospection.activity_types')[$activity->type] ?? $activity->type }} · {{ $activity->user?->name ?? 'Système' }}</div>
                        <div class="body">{{ $activity->body }}</div>
                    </li>
                @empty
                    <li class="muted">Aucune activité.</li>
                @endforelse
            </ul>
            {{ $activities->fragment('historique')->links('partials.pagination') }}
        </section>
    </div>

    <div class="stack">
        @can('convert', $company)
            <section class="card">
                <h2><x-icon name="target" /> Conversion en client</h2>
                <p class="small muted">La conversion est définitive et tracée. Les prospects et les clients restent gérés séparément.</p>
                <form method="POST" action="{{ route('pm.companies.convert', $company) }}">
                    @csrf
                    <label class="checkbox"><input type="checkbox" name="confirm" value="1" required> Je confirme la signature avec ce client</label>
                    <div class="form-actions"><button type="submit" class="btn btn-primary">Convertir en client</button></div>
                </form>
            </section>
        @endcan

        <section class="card">
            <h2>Traçabilité</h2>
            <dl class="details">
                <dt>Créé le</dt><dd>{{ $company->created_at->format('d/m/Y H:i') }}</dd>
                <dt>Modifié le</dt><dd>{{ $company->updated_at->format('d/m/Y H:i') }}</dd>
            </dl>
        </section>

        @if ($audit->isNotEmpty())
            <section class="card">
                <h2><x-icon name="shield-check" /> Journal d'audit</h2>
                <ul class="timeline">
                    @foreach ($audit as $entry)
                        <li>
                            <div class="meta">{{ $entry->created_at->format('d/m/Y H:i') }} · {{ $entry->user?->name ?? 'Système' }}</div>
                            <div class="small"><code>{{ $entry->action }}</code></div>
                            @if ($entry->changes)
                                <div class="small muted">{{ implode(', ', array_keys($entry->changes)) }}</div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
</div>
@endsection
