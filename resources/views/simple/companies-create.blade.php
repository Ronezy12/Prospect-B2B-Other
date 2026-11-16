@extends('layouts.app')
@section('title', 'Ajouter un prospect')

@section('content')
<a class="s-back" data-back href="{{ route('pm.companies.index') }}"><x-icon name="chevron-right" class="flip" /> Retour</a>
<h1 class="s-h1">Ajouter un prospect</h1>
<p class="s-lead">Seul le nom de l'entreprise est obligatoire. Vous pourrez compléter plus tard.</p>

@if ($duplicate = session('duplicate'))
    <div class="alert alert-warning" role="alert"><x-icon name="circle-alert" /><div>
        <strong>Cette entreprise existe peut-être déjà :</strong> « {{ $duplicate['name'] }} ».
        @if ($duplicate['id'] && ! $duplicate['trashed'] && ! $duplicate['client'])<a href="{{ route('pm.companies.show', $duplicate['id']) }}">Ouvrir sa fiche</a>.@endif
        Si c'est une autre entreprise, cochez la case en bas puis enregistrez.
    </div></div>
@endif

<form method="POST" action="{{ route('pm.companies.store') }}" class="s-card s-form s-form-wide">
    @csrf
    <input type="hidden" name="status" value="nouveau">
    <div class="s-form-cols">
        <fieldset class="s-fieldset">
            <legend><x-icon name="building-2" /> L'entreprise</legend>
            <div class="field"><label for="name">Nom de l'entreprise *</label><input type="text" id="name" name="name" value="{{ old('name') }}" required maxlength="190" placeholder="Ex. : Agence du Parc" autofocus>@error('name')<div class="error">{{ $message }}</div>@enderror</div>
            <div class="field"><label for="sector_id">Secteur</label><select id="sector_id" name="sector_id"><option value="">Choisir…</option>@foreach ($sectors as $s)<option value="{{ $s->id }}" @selected(old('sector_id') == $s->id)>{{ $s->name }}</option>@endforeach</select><div class="s-hint">Le secteur permet de proposer les bons modèles d'e-mails.</div>@error('sector_id')<div class="error">{{ $message }}</div>@enderror</div>
            <div class="field"><label for="address">Adresse postale</label><input type="text" id="address" name="address" value="{{ old('address') }}" maxlength="255" placeholder="Ex. : 12 rue de la République" autocomplete="street-address">@error('address')<div class="error">{{ $message }}</div>@enderror</div>
            <div class="s-cp-city">
                <div class="field"><label for="postal_code">Code postal</label><input type="text" id="postal_code" name="postal_code" value="{{ old('postal_code') }}" maxlength="12" inputmode="numeric" placeholder="75001" autocomplete="postal-code">@error('postal_code')<div class="error">{{ $message }}</div>@enderror</div>
                <div class="field"><label for="city">Ville</label><input type="text" id="city" name="city" value="{{ old('city') }}" maxlength="120" placeholder="Paris" autocomplete="address-level2">@error('city')<div class="error">{{ $message }}</div>@enderror</div>
            </div>
        </fieldset>
        <fieldset class="s-fieldset">
            <legend><x-icon name="user-round" /> La personne à contacter</legend>
            <div class="s-two">
                <div class="field"><label for="first">Prénom</label><input type="text" id="first" name="contact[first_name]" value="{{ old('contact.first_name') }}" maxlength="100"></div>
                <div class="field"><label for="last">Nom</label><input type="text" id="last" name="contact[last_name]" value="{{ old('contact.last_name') }}" maxlength="100"></div>
            </div>
            <div class="field"><label for="phone">Téléphone</label><input type="tel" id="phone" name="contact[phone]" value="{{ old('contact.phone') }}" maxlength="40" placeholder="06 12 34 56 78">@error('contact.phone')<div class="error">{{ $message }}</div>@enderror</div>
            <div class="field"><label for="email">E-mail</label><input type="email" id="email" name="contact[email]" value="{{ old('contact.email') }}" maxlength="190" placeholder="prenom@entreprise.fr">@error('contact.email')<div class="error">{{ $message }}</div>@enderror</div>
        </fieldset>
    </div>
    @if (session('duplicate'))<label class="checkbox"><input type="checkbox" name="confirm_duplicate" value="1"> C'est une autre entreprise, créer quand même</label>@endif
    <button type="submit" class="btn btn-primary s-submit"><x-icon name="check" /> Enregistrer le prospect</button>
</form>
@can('prospects.import')<p class="s-hint">Beaucoup de prospects à ajouter ? <a href="{{ route('pm.companies.transfer') }}">Importez un fichier Excel ou CSV</a>.</p>@endcan
@endsection
