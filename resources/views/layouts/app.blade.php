<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Prospection') — {{ config('app.name') }}</title>
    <link rel="preload" href="{{ asset('fonts/inter-latin-wght-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset('css/pm.css') }}?v=6">
    <script src="{{ asset('js/theme.js') }}?v=6"></script>
    <script src="{{ asset('js/pm.js') }}?v=6" defer></script>
    @stack('head')
</head>
<body>
@php($u = auth()->user())
@php($overdueTasks = \App\Models\Task::where('assignee_id', $u->id)->where('status', 'a_faire')->where('due_at', '<', now())->count())
@php($unreadSocial = $u->can('social.inbox') ? \App\Models\SocialConversation::visibleTo($u)->where('unread', true)->count() : 0)
@php($unreadNotifications = \App\Models\UserNotification::where('user_id', $u->id)->whereNull('read_at')->count())
<div class="app">
    <aside class="sidebar" id="sidebar" aria-label="Navigation principale">
        <div class="brand">
            <span class="brand-mark">AR</span>
            <span class="brand-name">Entreprise AR<small>Prospection &amp; Marketing</small></span>
        </div>
        <nav>
            <a href="{{ route('pm.dashboard') }}" @class(['nav-link', 'active' => request()->routeIs('pm.dashboard')])><x-icon name="layout-dashboard" /> Tableau de bord</a>
            <a href="{{ route('pm.notifications.index') }}" @class(['nav-link', 'active' => request()->routeIs('pm.notifications.*')])><x-icon name="alarm-clock" /> Notifications @if ($unreadNotifications)<span class="nav-count" title="Non lues">{{ $unreadNotifications }}</span>@endif</a>

            <div class="nav-section">Prospection</div>
            <a href="{{ route('pm.companies.index') }}" @class(['nav-link', 'active' => request()->routeIs('pm.companies.*')])><x-icon name="building-2" /> Base de prospects</a>
            <a href="{{ route('pm.contacts.index') }}" @class(['nav-link', 'active' => request()->routeIs('pm.contacts.*')])><x-icon name="contact-round" /> Contacts</a>
            @can('prospects.import')
                <a href="{{ route('pm.imports.index') }}" @class(['nav-link', 'active' => request()->routeIs('pm.imports.*')])><x-icon name="upload" /> Imports</a>
            @endcan
            <a href="{{ route('pm.pipelines.show') }}" @class(['nav-link', 'active' => request()->routeIs('pm.pipelines.*', 'pm.opportunities.*')])><x-icon name="kanban" /> Pipelines</a>
            @can('social.use')
                <a href="{{ route('pm.social.index') }}" @class(['nav-link', 'active' => request()->routeIs('pm.social.index', 'pm.social.show', 'pm.social.templates.*')])><x-icon name="share-2" /> Réseaux sociaux</a>
            @endcan
            @can('social.inbox')
                <a href="{{ route('pm.social.inbox.index') }}" @class(['nav-link', 'active' => request()->routeIs('pm.social.inbox.*')])><x-icon name="messages-square" /> Messages de la Page @if ($unreadSocial)<span class="nav-count" title="Conversations non lues">{{ $unreadSocial }}</span>@endif</a>
            @endcan
            <a href="{{ route('pm.tasks.index') }}" @class(['nav-link', 'active' => request()->routeIs('pm.tasks.*')])><x-icon name="list-checks" /> Tâches et relances @if ($overdueTasks)<span class="nav-count" title="Tâches en retard">{{ $overdueTasks }}</span>@endif</a>

            @canany(['campaigns.view', 'automations.manage', 'reports.view'])<div class="nav-section">Marketing</div>@endcanany
            @can('campaigns.view')
                <a href="{{ route('pm.campaigns.index') }}" @class(['nav-link', 'active' => request()->routeIs('pm.campaigns.*')])><x-icon name="mail" /> Campagnes</a>
                <a href="{{ route('pm.newsletters.index') }}" @class(['nav-link', 'active' => request()->routeIs('pm.newsletters.*')])><x-icon name="newspaper" /> Newsletters</a>
                <a href="{{ route('pm.templates.index') }}" @class(['nav-link', 'active' => request()->routeIs('pm.templates.*')])><x-icon name="layers" /> Modèles d'e-mails</a>
                <a href="{{ route('pm.segments.index') }}" @class(['nav-link', 'active' => request()->routeIs('pm.segments.*')])><x-icon name="filter" /> Segments</a>
            @endcan
            @can('automations.manage')
                <a href="{{ route('pm.automations.index') }}" @class(['nav-link', 'active' => request()->routeIs('pm.automations.*')])><x-icon name="sparkles" /> Automatisations</a>
            @endcan
            @can('reports.view')
                <a href="{{ route('pm.reports.index') }}" @class(['nav-link', 'active' => request()->routeIs('pm.reports.*')])><x-icon name="chart-column" /> Statistiques</a>
            @endcan

            @canany(['users.manage', 'pipelines.manage', 'settings.manage'])
                <div class="nav-section">Administration</div>
                @can('pipelines.manage')
                    <a href="{{ route('pm.settings.pipelines.index') }}" @class(['nav-link', 'active' => request()->routeIs('pm.settings.pipelines.*')])><x-icon name="settings" /> Secteurs et pipelines</a>
                @endcan
                @can('settings.manage')
                    <a href="{{ route('pm.settings.mail.index') }}" @class(['nav-link', 'active' => request()->routeIs('pm.settings.mail.*')])><x-icon name="inbox" /> Comptes d'envoi</a>
                    <a href="{{ route('pm.settings.inboxes.index') }}" @class(['nav-link', 'active' => request()->routeIs('pm.settings.inboxes.*')])><x-icon name="mail" /> Réponses (IMAP)</a>
                    <a href="{{ route('pm.settings.social.index') }}" @class(['nav-link', 'active' => request()->routeIs('pm.settings.social.*')])><x-icon name="share-2" /> Connexion Meta</a>
                    <a href="{{ route('pm.settings.tracking.edit') }}" @class(['nav-link', 'active' => request()->routeIs('pm.settings.tracking.*')])><x-icon name="shield-check" /> Suivi des e-mails</a>
                @endcan
                @can('users.manage')
                    <a href="{{ route('pm.settings.users.index') }}" @class(['nav-link', 'active' => request()->routeIs('pm.settings.users.*')])><x-icon name="users" /> Utilisateurs et rôles</a>
                @endcan
            @endcanany
        </nav>
        <div class="sidebar-user">
            <x-avatar :user="$u" />
            <div class="who"><strong>{{ $u->name }}</strong><span>{{ $u->email }}</span></div>
            <button type="button" class="icon-btn" data-theme-toggle title="Changer de thème" aria-label="Changer de thème"><x-icon name="moon" /></button>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="icon-btn" title="Se déconnecter" aria-label="Se déconnecter"><x-icon name="log-out" /></button>
            </form>
        </div>
    </aside>

    <div class="main">
        <div class="topbar">
            <button type="button" class="icon-btn" data-sidebar-toggle aria-controls="sidebar" aria-expanded="false" aria-label="Ouvrir le menu"><x-icon name="menu" /></button>
            <span class="brand-mark">AR</span>
            <strong>Prospection</strong>
        </div>
        <main class="content" id="contenu">
            @include('partials.flash')
            @yield('content')
        </main>
    </div>
</div>
</body>
</html>
