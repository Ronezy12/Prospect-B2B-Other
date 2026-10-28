@extends('layouts.app')
@section('title', $user->exists ? 'Modifier un utilisateur' : 'Nouvel utilisateur')

@section('content')
<div class="page-header">
    <div>
        <div class="breadcrumb"><a href="{{ route('pm.settings.users.index') }}">Utilisateurs et rôles</a></div>
        <h1>{{ $user->exists ? $user->name : 'Nouvel utilisateur' }}</h1>
    </div>
</div>

<form method="POST" action="{{ $user->exists ? route('pm.settings.users.update', $user) : route('pm.settings.users.store') }}" class="card">
    @csrf
    @if ($user->exists) @method('PUT') @endif
    <div class="form-grid">
        <div class="field">
            <label for="name">Nom *</label>
            <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" required maxlength="120">
        </div>
        <div class="field">
            <label for="email">E-mail *</label>
            <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" required maxlength="190" autocomplete="off">
            @error('email')<div class="error">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label for="password">Mot de passe {{ $user->exists ? '(laisser vide pour conserver)' : '*' }}</label>
            <input type="password" id="password" name="password" @required(! $user->exists) autocomplete="new-password">
            <div class="hint">12 caractères minimum, majuscules, minuscules et chiffres.</div>
            @error('password')<div class="error">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label for="password_confirmation">Confirmation</label>
            <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password">
        </div>
        <div class="field">
            <label for="role">Rôle *</label>
            <select id="role" name="role" required>
                @foreach ($roleLabels as $key => $label)
                    <option value="{{ $key }}" @selected(old('role', $user->roles->first()?->name ?? 'commercial') === $key)>{{ $label }}</option>
                @endforeach
            </select>
            @error('role')<div class="error">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label for="team_id">Équipe</label>
            <select id="team_id" name="team_id">
                <option value="">Aucune</option>
                @foreach ($teams as $team)<option value="{{ $team->id }}" @selected(old('team_id', $user->team_id) == $team->id)>{{ $team->name }}</option>@endforeach
            </select>
        </div>
        <div class="field">
            <label for="new_team">… ou nouvelle équipe</label>
            <input type="text" id="new_team" name="new_team" value="{{ old('new_team') }}" maxlength="120">
        </div>
        <div class="field">
            <label class="checkbox"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active))> Compte actif</label>
        </div>
    </div>
    <div class="form-actions">
        <a class="btn" href="{{ route('pm.settings.users.index') }}">Annuler</a>
        <button type="submit" class="btn btn-primary">Enregistrer</button>
    </div>
</form>
@endsection
