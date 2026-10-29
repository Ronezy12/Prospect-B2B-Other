@extends('layouts.app')
@section('title', $contact->exists ? 'Modifier un contact' : 'Nouvel interlocuteur')

@section('content')
<div class="page-header">
    <div>
        <div class="breadcrumb"><a href="{{ route('pm.companies.show', $company) }}">{{ $company->name }}</a></div>
        <h1>{{ $contact->exists ? 'Modifier '.$contact->fullName() : 'Nouvel interlocuteur' }}</h1>
    </div>
</div>

<form method="POST" action="{{ $contact->exists ? route('pm.contacts.update', $contact) : route('pm.contacts.store') }}" class="card">
    @csrf
    @if ($contact->exists) @method('PUT') @endif
    <input type="hidden" name="company_id" value="{{ $company->id }}">

    <fieldset>
        <legend><x-icon name="contact-round" /> Identité et coordonnées professionnelles</legend>
        <div class="form-grid">
            <div class="field">
                <label for="civility">Civilité</label>
                <select id="civility" name="civility">
                    <option value="">—</option>
                    @foreach (['M.', 'Mme'] as $c)
                        <option value="{{ $c }}" @selected(old('civility', $contact->civility) === $c)>{{ $c }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="job_title">Fonction</label>
                <input type="text" id="job_title" name="job_title" value="{{ old('job_title', $contact->job_title) }}" maxlength="150">
            </div>
            <div class="field">
                <label for="first_name">Prénom</label>
                <input type="text" id="first_name" name="first_name" value="{{ old('first_name', $contact->first_name) }}" maxlength="100">
                @error('first_name')<div class="error">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="last_name">Nom</label>
                <input type="text" id="last_name" name="last_name" value="{{ old('last_name', $contact->last_name) }}" maxlength="100">
            </div>
            <div class="field">
                <label for="email">E-mail</label>
                <input type="email" id="email" name="email" value="{{ old('email', $contact->email) }}" maxlength="190" @class(['is-invalid' => $errors->has('email')])>
                @error('email')<div class="error">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="phone">Téléphone</label>
                <input type="tel" id="phone" name="phone" value="{{ old('phone', $contact->phone) }}" maxlength="40">
                @error('phone')<div class="error">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="mobile">Mobile</label>
                <input type="tel" id="mobile" name="mobile" value="{{ old('mobile', $contact->mobile) }}" maxlength="40">
                @error('mobile')<div class="error">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="linkedin_url">Profil LinkedIn</label>
                <input type="url" id="linkedin_url" name="linkedin_url" value="{{ old('linkedin_url', $contact->linkedin_url) }}" maxlength="255" placeholder="https://www.linkedin.com/in/…">
                @error('linkedin_url')<div class="error">{{ $message }}</div>@enderror
            </div>
            <div class="field full">
                <label class="checkbox"><input type="hidden" name="is_primary" value="0"><input type="checkbox" name="is_primary" value="1" @checked(old('is_primary', $contact->is_primary))> Interlocuteur principal</label>
            </div>
        </div>
    </fieldset>

    <fieldset>
        <legend><x-icon name="shield-check" /> Conformité (RGPD)</legend>
        <div class="form-grid">
            <div class="field">
                <label for="audience_type">Type de contact *</label>
                <select id="audience_type" name="audience_type" required>
                    @foreach (config('prospection.audience_types') as $key => $label)
                        <option value="{{ $key }}" @selected(old('audience_type', $contact->audience_type) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="legal_basis">Base légale *</label>
                <select id="legal_basis" name="legal_basis" required>
                    @foreach (config('prospection.legal_bases') as $key => $label)
                        <option value="{{ $key }}" @selected(old('legal_basis', $contact->legal_basis) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="source_id">Source de la donnée</label>
                <select id="source_id" name="source_id">
                    <option value="">—</option>
                    @foreach ($sources as $source)
                        <option value="{{ $source->id }}" @selected(old('source_id', $contact->source_id) == $source->id)>{{ $source->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="collected_at">Date de collecte</label>
                <input type="date" id="collected_at" name="collected_at" value="{{ old('collected_at', $contact->collected_at?->format('Y-m-d')) }}">
            </div>
            <div class="field full">
                <label class="checkbox"><input type="hidden" name="tracking_consent" value="0"><input type="checkbox" name="tracking_consent" value="1" @checked(old('tracking_consent', (bool) $contact->tracking_consent_at))> Consent à la mesure d'audience de nos e-mails (ouvertures et clics)</label>
                <input type="text" id="tracking_consent_source" name="tracking_consent_source" value="{{ old('tracking_consent_source', $contact->tracking_consent_source) }}" maxlength="255" placeholder="Preuve : ex. case cochée sur le formulaire du salon 2026" aria-label="Source du consentement">
                @if ($contact->tracking_consent_at)<div class="hint">Consentement enregistré le {{ $contact->tracking_consent_at->format('d/m/Y') }}.</div>@endif
                @error('tracking_consent_source')<div class="error">{{ $message }}</div>@enderror
            </div>
            <div class="field full">
                <label for="collection_note">Contexte de collecte / information de la personne</label>
                <input type="text" id="collection_note" name="collection_note" value="{{ old('collection_note', $contact->collection_note) }}" maxlength="255">
            </div>
            <div class="field full">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes" maxlength="10000">{{ old('notes', $contact->notes) }}</textarea>
            </div>
        </div>
    </fieldset>

    <div class="form-actions">
        <a class="btn" href="{{ route('pm.companies.show', $company) }}">Annuler</a>
        <button type="submit" class="btn btn-primary"><x-icon name="check" /> Enregistrer</button>
    </div>
</form>
@endsection
