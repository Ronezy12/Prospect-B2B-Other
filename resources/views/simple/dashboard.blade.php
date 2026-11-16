@extends('layouts.app')
@section('title', 'Ma journée')

@section('content')
<header class="s-top">
    <div>
        <div class="s-date">{{ ucfirst(now()->locale('fr')->isoFormat('dddd D MMMM')) }}</div>
        <h1>Bonjour {{ \Illuminate\Support\Str::before(auth()->user()->name, ' ') }}</h1>
        <p class="s-lead">Appelez, puis indiquez le résultat : le suivi et les rappels se font automatiquement.</p>
    </div>
    <div class="s-top-actions">
        @can('campaigns.manage')<a class="btn" href="{{ route('pm.simple-email.create') }}"><x-icon name="mail" /> Écrire un e-mail</a>@endcan
        @can('prospects.create')<a class="btn btn-primary" href="{{ route('pm.companies.create') }}"><x-icon name="plus" /> Ajouter un prospect</a>@endcan
    </div>
</header>

<div class="s-kpis">
    <a class="s-kpi" href="{{ route('pm.dashboard', ['vue' => 'appels']) }}"><span class="s-kpi-icon"><x-icon name="phone-call" /></span><strong>{{ $calls->count() }}</strong><span>à appeler</span></a>
    @isset($views['reseaux'])<a class="s-kpi" href="{{ route('pm.dashboard', ['vue' => 'reseaux']) }}"><span class="s-kpi-icon"><x-icon name="share-2" /></span><strong>{{ $social->count() }}</strong><span>réseaux à traiter</span></a>@endisset
    <a class="s-kpi" href="{{ route('pm.dashboard', ['vue' => 'rdv']) }}"><span class="s-kpi-icon"><x-icon name="calendar-days" /></span><strong>{{ $appointments->count() }}</strong><span>rendez-vous à venir</span></a>
    <a class="s-kpi" href="{{ route('pm.companies.index') }}"><span class="s-kpi-icon"><x-icon name="briefcase" /></span><strong>{{ $openDeals }}</strong><span>affaires en cours</span></a>
</div>

<nav class="s-tabs" aria-label="Listes">
    @foreach ($views as $key => $label)
        @php($n = ['appels' => $calls->count(), 'reseaux' => $social->count(), 'rdv' => $appointments->count()][$key])
        <a href="{{ route('pm.dashboard', ['vue' => $key]) }}" @class(['s-tab', 'active' => $view === $key])>{{ $label }} <span>{{ $n }}</span></a>
    @endforeach
</nav>

@if ($view === 'appels')
    @forelse ($calls as $item)
        @include('simple.call-card', ['company' => $item['company'], 'why' => $item['why'], 'late' => $item['late'], 'new' => $item['new']])
    @empty
        <div class="s-empty"><x-icon name="circle-check" /><strong>Aucun appel prévu</strong><span>Les rappels apparaissent ici automatiquement après chaque résultat.</span></div>
    @endforelse
@elseif ($view === 'reseaux')
    <p class="s-note-line"><x-icon name="shield-check" /> Contactez la personne sur le réseau, puis indiquez ce que vous avez fait. Rien n'est envoyé à votre place.</p>
    @forelse ($social as $profile)
        @include('simple.social-card', ['profile' => $profile])
    @empty
        <div class="s-empty"><x-icon name="share-2" /><strong>Rien à traiter sur les réseaux</strong><span>Ajoutez le profil LinkedIn ou Facebook d'un prospect depuis sa fiche.</span></div>
    @endforelse
@else
    @forelse ($appointments as $task)
        @php($contact = $task->company?->contacts->first())
        @php($phone = $contact?->mobile ?? $contact?->phone ?? $task->company?->phone)
        <article class="s-card s-rdv">
            <div class="s-date-block"><strong>{{ $task->due_at->format('d') }}</strong><span>{{ $task->due_at->locale('fr')->isoFormat('MMM') }}</span></div>
            <div class="s-card-id">
                <div class="s-name">{{ $task->company?->name ?? $task->title }}</div>
                <div class="s-sub">{{ ucfirst($task->due_at->locale('fr')->isoFormat('dddd [à] HH[h]mm')) }}@if ($contact) · {{ $contact->fullName() }}@endif</div>
            </div>
            @if ($phone)<a class="s-call neutral" href="tel:{{ $phone }}"><x-icon name="phone" /><span>Appeler</span></a>@endif
            @if ($task->company)<a class="btn btn-sm" href="{{ route('pm.companies.show', $task->company) }}">Fiche</a>@endif
        </article>
    @empty
        <div class="s-empty"><x-icon name="calendar-days" /><strong>Aucun rendez-vous prévu</strong><span>Ils s'ajoutent quand vous indiquez « Rendez-vous » après un appel.</span></div>
    @endforelse
@endif
@endsection
