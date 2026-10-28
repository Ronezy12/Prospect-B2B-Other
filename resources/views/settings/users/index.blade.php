@extends('layouts.app')
@section('title', 'Utilisateurs et rôles')

@section('content')
<div class="page-header">
    <div>
        <h1>Utilisateurs et rôles</h1>
        <p class="subtitle">Comptes, rôles et équipes. Les droits sont vérifiés côté serveur.</p>
    </div>
    <div class="actions"><a class="btn btn-primary" href="{{ route('pm.settings.users.create') }}"><x-icon name="user-plus" /> Nouvel utilisateur</a></div>
</div>

<div class="card flush">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Nom</th><th>E-mail</th><th>Rôle</th><th>Équipe</th><th>État</th><th>Dernière connexion</th><th></th></tr></thead>
            <tbody>
            @foreach ($users as $user)
                <tr>
                    <td><span class="person"><x-avatar :user="$user" size="sm" /> <span class="row-title">{{ $user->name }}</span></span></td>
                    <td class="muted">{{ $user->email }}</td>
                    <td><span class="badge badge-primary plain">{{ $user->roles->map(fn ($r) => $roleLabels[$r->name] ?? $r->name)->implode(', ') ?: '—' }}</span></td>
                    <td>{{ $user->team?->name ?? '—' }}</td>
                    <td>@if ($user->is_active)<span class="badge badge-success">Actif</span>@else<span class="badge badge-danger">Désactivé</span>@endif</td>
                    <td>{{ $user->last_login_at?->format('d/m/Y H:i') ?? 'Jamais' }}</td>
                    <td><a class="btn btn-sm" href="{{ route('pm.settings.users.edit', $user) }}"><x-icon name="pencil" /> Modifier</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    {{ $users->links('partials.pagination') }}
</div>
@endsection
