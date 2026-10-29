<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Prospection') — {{ config('app.name') }}</title>
    <link rel="preload" href="{{ asset('fonts/inter-latin-wght-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="stylesheet" href="{{ asset('css/pm.css') }}?v=8">
    <script src="{{ asset('js/theme.js') }}?v=8"></script>
    <script src="{{ asset('js/pm.js') }}?v=8" defer></script>
    @stack('head')
</head>
<body>
@php($u = auth()->user())
@php($overdueTasks = \App\Models\Task::where('assignee_id', $u->id)->where('status', 'a_faire')->where('due_at', '<', now())->count())
@php($unreadSocial = $u->can('social.inbox') ? \App\Models\SocialConversation::visibleTo($u)->where('unread', true)->count() : 0)
@php($unreadNotifications = \App\Models\UserNotification::where('user_id', $u->id)->whereNull('read_at')->count())
@php($sections = \App\Support\Navigation::sections($u))
@php($currentSection = \App\Support\Navigation::current($sections))
@php($counts = ['taches' => $overdueTasks, 'social' => $unreadSocial])
@php($countTitles = ['taches' => 'Tâches en retard', 'social' => 'Messages non lus'])
<div class="app">
    <aside class="sidebar" id="sidebar" aria-label="Navigation principale">
        <div class="brand">
            <img class="brand-logo" src="{{ asset('img/logo-mark.png') }}" alt="" width="40" height="40">
            <span class="brand-name">Entreprise AR<small>Prospection &amp; Marketing</small></span>
        </div>
        <nav>
            @foreach ($sections as $section)
                <a href="{{ route($section['route']) }}" @class(['nav-link', 'active' => request()->routeIs(...$section['active'])])>
                    <x-icon :name="$section['icon']" /> {{ $section['label'] }}
                    @if (($counts[$section['key']] ?? 0) > 0)<span class="nav-count" title="{{ $countTitles[$section['key']] }}">{{ $counts[$section['key']] }}</span>@endif
                </a>
            @endforeach
        </nav>
        <div class="sidebar-user">
            <x-avatar :user="$u" />
            <div class="who"><strong>{{ $u->name }}</strong><span>{{ $u->email }}</span></div>
            <a href="{{ route('pm.notifications.index') }}" class="icon-btn bell" title="Notifications" aria-label="Notifications{{ $unreadNotifications ? ' ('.$unreadNotifications.' non lues)' : '' }}"><x-icon name="bell" />@if ($unreadNotifications)<span class="dot"></span>@endif</a>
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
            <img class="brand-logo" src="{{ asset('img/logo-mark.png') }}" alt="" width="32" height="32">
            <strong>Prospection</strong>
        </div>
        <main class="content" id="contenu">
            @if ($currentSection && count($currentSection['tabs']) > 1)
                <nav class="tabs subnav" aria-label="{{ $currentSection['label'] }}">
                    @foreach ($currentSection['tabs'] as $tab)
                        <a href="{{ route($tab['route']) }}" @class(['tab', 'active' => request()->routeIs(...$tab['active'])])>{{ $tab['label'] }}</a>
                    @endforeach
                </nav>
            @endif
            @if ($currentSection && ! empty($currentSection['help']) && collect($currentSection['tabs'])->pluck('route')->push($currentSection['route'])->contains(request()->route()?->getName()))
                <p class="section-help"><x-icon name="circle-help" /> <span>{{ $currentSection['help'] }}</span> <a href="{{ route('pm.guide') }}#{{ $currentSection['guide'] }}">Comprendre en 1 minute</a></p>
            @endif
            @include('partials.flash')
            @yield('content')
        </main>
    </div>
</div>
</body>
</html>
