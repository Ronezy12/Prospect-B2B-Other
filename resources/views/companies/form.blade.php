@extends('layouts.app')
@section('title', $company->exists ? 'Modifier '.$company->name : 'Nouveau prospect')

@section('content')
<div class="page-header">
    <div>
        <div class="breadcrumb"><a href="{{ route('pm.companies.index') }}">Base de prospects</a></div>
        <h1>{{ $company->exists ? 'Modifier « '.$company->name.' »' : 'Nouveau prospect' }}</h1>
    </div>
</div>

@if ($duplicate = session('duplicate'))
    <div class="alert alert-warning" role="alert">
        <strong>Fiche similaire détectée :</strong> « {{ $duplicate['name'] }} »
        @if ($duplicate['trashed']) (fiche supprimée)
        @elseif ($duplicate['client']) (déjà client)
        @elseif ($duplicate['id']) — <a href="{{ route('pm.companies.show', $duplicate['id']) }}">ouvrir la fiche existante</a>
        @endif.
        Si ce n'est pas la même entreprise, cochez « Créer malgré la ressemblance » en bas du formulaire.
    </div>
@endif

<form method="POST" action="{{ $company->exists ? route('pm.companies.update', $company) : route('pm.companies.store') }}" class="card">
    @csrf
    @if ($company->exists) @method('PUT') @endif

    <fieldset>
        <legend>Entreprise</legend>
        <div class="form-grid">
            <div class="field">
                <label for="name">Raison sociale *</label>
                <input type="text" id="name" name="name" value="{{ old('name', $company->name) }}" required maxlength="190" @class(['is-invalid' => $errors->has('name')])>
                @error('name')<div class="error">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="registration_number">SIREN / SIRET</label>
                <input type="text" id="registration_number" name="registration_number" value="{{ old('registration_number', $company->registration_number) }}" inputmode="numeric" maxlength="20">
                @error('registration_number')<div class="error">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="sector_id">Secteur</label>
                <select id="sector_id" name="sector_id" data-sector-select>
                    <option value="">—</option>
                    @foreach ($sectors as $sector)
                        <option value="{{ $sector->id }}" @selected(old('sector_id', $company->sector_id) == $sector->id)>{{ $sector->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="subsector_id">Sous-secteur</label>
                <select id="subsector_id" name="subsector_id" data-subsector-select>
                    <option value="">—</option>
                    @foreach ($sectors as $sector)
                        @foreach ($sector->children as $child)
                            <option value="{{ $child->id }}" data-parent="{{ $sector->id }}" @selected(old('subsector_id', $company->subsector_id) == $child->id)>{{ $child->name }}</option>
                        @endforeach
                    @endforeach
                </select>
                @error('subsector_id')<div class="error">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="website">Site web</label>
                <input type="text" id="website" name="website" value="{{ old('website', $company->website) }}" maxlength="255" placeholder="www.exemple.fr">
                @error('website')<div class="error">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="email">E-mail générique</label>
                <input type="email" id="email" name="email" value="{{ old('email', $company->email) }}" maxlength="190">
                @error('email')<div class="error">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="phone">Téléphone</label>
                <input type="tel" id="phone" name="phone" value="{{ old('phone', $company->phone) }}" maxlength="40">
                @error('phone')<div class="error">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="address">Adresse</label>
                <input type="text" id="address" name="address" value="{{ old('address', $company->address) }}" maxlength="255">
            </div>
            <div class="field">
                <label for="postal_code">Code postal</label>
                <input type="text" id="postal_code" name="postal_code" value="{{ old('postal_code', $company->postal_code) }}" maxlength="12">
            </div>
            <div class="field">
                <label for="city">Ville</label>
                <input type="text" id="city" name="city" value="{{ old('city', $company->city) }}" maxlength="120">
            </div>
            <div class="field">
                <label for="country">Pays (code ISO)</label>
                <input type="text" id="country" name="country" value="{{ old('country', $company->country ?? 'FR') }}" maxlength="2">
            </div>
        </div>
    </fieldset>

    <fieldset>
        <legend>Suivi commercial</legend>
        <div class="form-grid">
            <div class="field">
                <label for="status">Statut *</label>
                <select id="status" name="status" required>
                    @foreach ($statuses as $key => $label)
                        <option value="{{ $key }}" @selected(old('status', $company->status) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="owner_id">Responsable</label>
                @can('prospects.assign')
                    <select id="owner_id" name="owner_id">
                        <option value="">Non assigné</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected(old('owner_id', $company->owner_id ?? auth()->id()) == $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                @else
                    <input type="text" id="owner_id" value="{{ $company->owner?->name ?? auth()->user()->name }}" disabled>
                    <div class="hint">L'assignation est réservée aux responsables.</div>
                @endcan
            </div>
            <div class="field">
                <label for="source_id">Source d'acquisition</label>
                <select id="source_id" name="source_id">
                    <option value="">—</option>
                    @foreach ($sources as $source)
                        <option value="{{ $source->id }}" @selected(old('source_id', $company->source_id) == $source->id)>{{ $source->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="acquired_at">Date d'acquisition</label>
                <input type="date" id="acquired_at" name="acquired_at" value="{{ old('acquired_at', $company->acquired_at?->format('Y-m-d')) }}">
            </div>
            <div class="field full">
                <label for="tags">Tags</label>
                <input type="text" id="tags" name="tags" value="{{ old('tags', $company->exists ? $company->tags->pluck('name')->implode(', ') : '') }}" maxlength="500" list="tag-list">
                <datalist id="tag-list">@foreach ($tags as $tag)<option value="{{ $tag->name }}">@endforeach</datalist>
                <div class="hint">Séparés par des virgules.</div>
            </div>
            <div class="field full">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes" maxlength="10000">{{ old('notes', $company->notes) }}</textarea>
            </div>
        </div>
    </fieldset>

    @unless ($company->exists)
    <fieldset>
        <legend>Premier interlocuteur (facultatif)</legend>
        <div class="form-grid">
            <div class="field">
                <label for="contact_first_name">Prénom</label>
                <input type="text" id="contact_first_name" name="contact[first_name]" value="{{ old('contact.first_name') }}" maxlength="100">
            </div>
            <div class="field">
                <label for="contact_last_name">Nom</label>
                <input type="text" id="contact_last_name" name="contact[last_name]" value="{{ old('contact.last_name') }}" maxlength="100">
            </div>
            <div class="field">
                <label for="contact_job_title">Fonction</label>
                <input type="text" id="contact_job_title" name="contact[job_title]" value="{{ old('contact.job_title') }}" maxlength="150">
            </div>
            <div class="field">
                <label for="contact_email">E-mail professionnel</label>
                <input type="email" id="contact_email" name="contact[email]" value="{{ old('contact.email') }}" maxlength="190">
                @error('contact.email')<div class="error">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="contact_phone">Téléphone</label>
                <input type="tel" id="contact_phone" name="contact[phone]" value="{{ old('contact.phone') }}" maxlength="40">
            </div>
            <div class="field">
                <label for="contact_audience_type">Type de contact</label>
                <select id="contact_audience_type" name="contact[audience_type]">
                    @foreach (config('prospection.audience_types') as $key => $label)
                        <option value="{{ $key }}" @selected(old('contact.audience_type', 'b2b') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="contact_legal_basis">Base légale du traitement</label>
                <select id="contact_legal_basis" name="contact[legal_basis]">
                    @foreach (config('prospection.legal_bases') as $key => $label)
                        <option value="{{ $key }}" @selected(old('contact.legal_basis', 'interet_legitime') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <div class="hint">En B2C, la prospection par e-mail exige un consentement préalable.</div>
            </div>
        </div>
    </fieldset>
    @endunless

    @if (session('duplicate'))
        <label class="checkbox"><input type="checkbox" name="confirm_duplicate" value="1"> Créer malgré la ressemblance avec une fiche existante</label>
    @endif

    <div class="form-actions">
        <a class="btn" href="{{ $company->exists ? route('pm.companies.show', $company) : route('pm.companies.index') }}">Annuler</a>
        <button type="submit" class="btn btn-primary">Enregistrer</button>
    </div>
</form>
@endsection
