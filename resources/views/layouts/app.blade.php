<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Prospection') — {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/pm.css') }}?v=1">
    <script src="{{ asset('js/pm.js') }}?v=1" defer></script>
</head>
<body>
<div class="app">
    <aside class="sidebar" id="sidebar" aria-label="Navigation principale">
        <div class="brand">Entreprise AR<small>Prospection &amp; Marketing</small></div>
        <nav>
            @php($u = auth()->user())
            <a href="{{ route('pm.dashboard') }}" @class(['active' => request()->routeIs('pm.dashboard')])>Tableau de bord</a>

            <div class="nav-section">Prospects</div>
            <a href="{{ route('pm.companies.index') }}" @class(['active' => request()->routeIs('pm.companies.*')])>Base de prospects</a>
            <a href="{{ route('pm.contacts.index') }}" @class(['active' => request()->routeIs('pm.contacts.*')])>Entreprises et contacts</a>
            @can('prospects.import')
                <a href="{{ route('pm.imports.index') }}" @class(['active' => request()->routeIs('pm.imports.*')])>Imports</a>
            @endcan

            @can('users.manage')
                <div class="nav-section">Paramètres</div>
                <a href="{{ route('pm.settings.users.index') }}" @class(['active' => request()->routeIs('pm.settings.users.*')])>Utilisateurs et rôles</a>
            @endcan
        </nav>
        <div class="user">
            <div>{{ $u->name }}</div>
            <div class="muted small">{{ $u->email }}</div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit">Se déconnecter</button>
            </form>
        </div>
    </aside>

    <div class="main">
        <div class="topbar">
            <button type="button" data-sidebar-toggle aria-controls="sidebar" aria-expanded="false">Menu</button>
            <strong>Prospection &amp; Marketing</strong>
        </div>
        <main class="content" id="contenu">
            @include('partials.flash')
            @yield('content')
        </main>
    </div>
</div>
</body>
</html>
