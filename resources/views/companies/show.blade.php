@extends('layouts.app')
@section('title', $company->name)

@section('content')
<div class="page-header">
    <div>
        <div class="breadcrumb"><a href="{{ route('pm.companies.index') }}">Base de prospects</a></div>
        <h1>{{ $company->name }}
            @if ($company->isClient())<span class="badge badge-success">Client depuis le {{ $company->converted_at?->format('d/m/Y') }}</span>
            @else<span class="badge badge-primary">{{ $company->statusLabel() }}</span>@endif
        </h1>
    </div>
    <div class="actions">
        @can('update', $company)
            <a class="btn" href="{{ route('pm.companies.edit', $company) }}">Modifier</a>
        @endcan
        @can('delete', $company)
            <form method="POST" action="{{ route('pm.companies.destroy', $company) }}" data-confirm="Supprimer cette fiche ? Elle sera archivée.">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-danger">Supprimer</button>
            </form>
        @endcan
    </div>
</div>

<div class="grid grid-sidebar">
    <div>
        <section class="card">
            <h2>Informations</h2>
            <dl class="details">
                <dt>SIREN / SIRET</dt><dd>{{ $company->registration_number ?? '—' }}</dd>
                <dt>Secteur</dt><dd>{{ $company->sector?->name ?? '—' }}@if ($company->subsector) › {{ $company->subsector->name }}@endif</dd>
                <dt>Site web</dt><dd>@if ($company->website)<a href="{{ $company->website }}" target="_blank" rel="noopener noreferrer nofollow">{{ $company->website }}</a>@else — @endif</dd>
                <dt>E-mail</dt><dd>{{ $company->email ?? '—' }}</dd>
                <dt>Téléphone</dt><dd>{{ $company->phone ?? '—' }}</dd>
                <dt>Adresse</dt><dd>{{ collect([$company->address, trim($company->postal_code.' '.$company->city), $company->country !== 'FR' ? $company->country : null])->filter()->implode(', ') ?: '—' }}</dd>
                <dt>Source</dt><dd>{{ $company->source?->name ?? '—' }}</dd>
                <dt>Acquis le</dt><dd>{{ $company->acquired_at?->format('d/m/Y') ?? '—' }}</dd>
                <dt>Responsable</dt><dd>{{ $company->owner?->name ?? 'Non assigné' }}</dd>
                <dt>Tags</dt><dd>@forelse ($company->tags as $tag)<span class="tag">{{ $tag->name }}</span>@empty — @endforelse</dd>
                @if ($company->notes)
                    <dt>Notes</dt><dd class="pre">{{ $company->notes }}</dd>
                @endif
            </dl>
        </section>

        <section class="card flush">
            <h2 class="card-title">Interlocuteurs</h2>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr><th>Interlocuteur</th><th>Fonction</th><th>Coordonnées</th><th>Conformité</th><th></th></tr>
                    </thead>
                    <tbody>
                    @forelse ($company->contacts as $contact)
                        <tr>
                            <td>{{ trim($contact->civility.' '.$contact->fullName()) }} @if ($contact->is_primary)<span class="badge">Principal</span>@endif</td>
                            <td>{{ $contact->job_title ?? '—' }}</td>
                            <td>
                                @if ($contact->email)<div>{{ $contact->email }}</div>@endif
                                @if ($contact->phone)<div>{{ $contact->phone }}</div>@endif
                                @if ($contact->mobile)<div>{{ $contact->mobile }}</div>@endif
                                @if ($contact->linkedin_url)<div><a href="{{ $contact->linkedin_url }}" target="_blank" rel="noopener noreferrer nofollow">Profil LinkedIn</a></div>@endif
                            </td>
                            <td>
                                <span class="badge">{{ strtoupper($contact->audience_type) }}</span>
                                <div class="small muted">{{ config('prospection.legal_bases')[$contact->legal_basis] ?? $contact->legal_basis }}</div>
                                @if ($contact->email && isset($suppressed[$contact->email]))
                                    <span class="badge badge-danger">Opposition enregistrée</span>
                                @endif
                                @if ($contact->audience_type === 'b2c' && $contact->legal_basis !== 'consentement')
                                    <span class="badge badge-warning">Consentement requis</span>
                                @endif
                            </td>
                            <td class="nowrap">
                                @can('update', $contact)
                                    <a class="btn btn-sm" href="{{ route('pm.contacts.edit', $contact) }}">Modifier</a>
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
                                                <button type="submit" class="btn btn-sm btn-danger">Enregistrer</button>
                                            </form>
                                        </details>
                                    @endif
                                @endcan
                                @can('delete', $contact)
                                    <form method="POST" action="{{ route('pm.contacts.destroy', $contact) }}" data-confirm="Supprimer ce contact ?">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn-link small">Supprimer</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="table-empty">Aucun interlocuteur.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @can('update', $company)
                <div class="pagination"><a class="btn btn-sm" href="{{ route('pm.contacts.create', ['company_id' => $company->id]) }}">Ajouter un interlocuteur</a></div>
            @endcan
        </section>

        <section class="card" id="historique">
            <h2>Historique</h2>
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
                    <div class="form-actions"><button type="submit" class="btn btn-primary">Ajouter à l'historique</button></div>
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

    <div>
        @can('convert', $company)
            <section class="card">
                <h2>Conversion en client</h2>
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
                <h2>Journal d'audit</h2>
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
