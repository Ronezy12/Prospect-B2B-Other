@extends('layouts.app')
@section('title', 'Ajouter un prospect')

@section('content')
<a class="s-back" href="{{ route('pm.companies.index') }}">‹ Mes prospects</a>
<h1 class="s-h1">Ajouter un prospect</h1>

@if ($duplicate = session('duplicate'))
    <div class="alert alert-warning" role="alert"><x-icon name="circle-alert" /><div>
        <strong>Cette entreprise existe peut-être déjà :</strong> « {{ $duplicate['name'] }} ».
        @if ($duplicate['id'] && ! $duplicate['trashed'] && ! $duplicate['client'])<a href="{{ route('pm.companies.show', $duplicate['id']) }}">Ouvrir sa fiche</a>.@endif
        Si c'est une autre entreprise, cochez la case en bas puis enregistrez.
    </div></div>
@endif

<form method="POST" action="{{ route('pm.companies.store') }}" class="s-card s-form">
    @csrf
    <input type="hidden" name="status" value="nouveau">
    <div class="field"><label for="name">Nom de l'entreprise *</label><input type="text" id="name" name="name" value="{{ old('name') }}" required maxlength="190" placeholder="Ex. : Agence du Parc" autofocus>@error('name')<div class="error">{{ $message }}</div>@enderror</div>
    <div class="field"><label for="city">Ville</label><input type="text" id="city" name="city" value="{{ old('city') }}" maxlength="120"></div>
    <div class="s-two">
        <div class="field"><label for="first">Prénom de la personne</label><input type="text" id="first" name="contact[first_name]" value="{{ old('contact.first_name') }}" maxlength="100"></div>
        <div class="field"><label for="last">Nom de la personne</label><input type="text" id="last" name="contact[last_name]" value="{{ old('contact.last_name') }}" maxlength="100"></div>
    </div>
    <div class="field"><label for="phone">Téléphone</label><input type="tel" id="phone" name="contact[phone]" value="{{ old('contact.phone') }}" maxlength="40" placeholder="06 12 34 56 78">@error('contact.phone')<div class="error">{{ $message }}</div>@enderror</div>
    <div class="field"><label for="email">E-mail</label><input type="email" id="email" name="contact[email]" value="{{ old('contact.email') }}" maxlength="190" placeholder="prenom@entreprise.fr">@error('contact.email')<div class="error">{{ $message }}</div>@enderror</div>
    @if (session('duplicate'))<label class="checkbox"><input type="checkbox" name="confirm_duplicate" value="1"> C'est une autre entreprise, créer quand même</label>@endif
    <button type="submit" class="s-submit">✅ Enregistrer</button>
</form>
@endsection
