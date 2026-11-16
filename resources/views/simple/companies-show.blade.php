@extends('layouts.app')
@section('title', $company->name)

@section('content')
@php($contact = $company->contacts->first())
@php($phone = $contact?->mobile ?? $contact?->phone ?? $company->phone)
@php($email = $contact?->email ?? $company->email)
<a class="s-back" href="{{ route('pm.companies.index') }}"><x-icon name="chevron-right" class="flip" /> Mes prospects</a>

<section class="s-card s-profile">
    <div class="s-card-head">
        <x-avatar :name="$company->name" />
        <div class="s-card-id">
            <h1>{{ $company->name }}</h1>
            <div class="s-sub">{{ $contact?->fullName() ?? 'Interlocuteur non renseigné' }}@if ($contact?->job_title) · {{ $contact->job_title }}@endif @if ($company->city) · {{ $company->city }}@endif</div>
        </div>
        <span class="s-chip s-chip-{{ $company->isClient() ? 'client' : $company->status }}">{{ $company->isClient() ? 'Client' : $company->statusLabel() }}</span>
    </div>
    <div class="s-actions">
        @if ($phone)<a class="s-call" href="tel:{{ $phone }}"><x-icon name="phone" /><span>Appeler · {{ \App\Support\Normalizer::displayPhone($phone) }}</span></a>@endif
        @if ($contact && $contact->email)
            @can('emails.individual')<a class="s-call neutral" href="{{ route('pm.contacts.email.create', $contact) }}"><x-icon name="mail" /><span>Envoyer un e-mail</span></a>@endcan
        @elseif ($email)
            <span class="s-sub"><x-icon name="mail" /> {{ $email }}</span>
        @endif
    </div>
    @if ($nextTask)
        <p class="s-next"><x-icon name="alarm-clock" /> Prochain rappel : <strong>{{ $nextTask->title }}</strong>, {{ $nextTask->due_at->locale('fr')->isoFormat('dddd D MMMM') }}</p>
    @endif
</section>

@unless ($company->isClient())
    <section class="s-card">
        <h2 class="s-title">Résultat de votre dernier appel</h2>
        @include('simple.outcomes', ['company' => $company])
        <p class="s-help">Le statut, l'affaire et le prochain rappel sont mis à jour automatiquement.</p>
    </section>
@endunless

@can('social.use')
    <section class="s-card">
        <h2 class="s-title"><x-icon name="share-2" /> Réseaux sociaux</h2>
        @forelse ($company->socialProfiles as $profile)
            <div class="s-line">
                @include('social.partials.platform', ['platform' => $profile->platform])
                <span class="s-line-main">{{ $profile->contact?->fullName() ?? 'Page de l\'entreprise' }} <span class="s-sub">· {{ $profile->statusLabel() }}</span></span>
                @unless ($profile->isOpposed())<a class="btn btn-sm" href="{{ $profile->url }}" target="_blank" rel="noopener noreferrer nofollow"><x-icon name="external-link" /> Ouvrir</a>@endunless
                <a class="btn btn-sm" href="{{ route('pm.social.show', $profile) }}">Suivi</a>
            </div>
        @empty
            <p class="s-sub">Aucun profil enregistré.</p>
        @endforelse
        <form method="POST" action="{{ route('pm.social.store', $company) }}" class="s-inline-form">
            @csrf
            <label class="sr-only" for="sp-platform">Réseau</label>
            <select id="sp-platform" name="platform">@foreach (config('prospection.social.platforms') as $key => $p)<option value="{{ $key }}">{{ $p['label'] }}</option>@endforeach</select>
            <label class="sr-only" for="sp-url">Adresse du profil</label>
            <input type="url" id="sp-url" name="url" required maxlength="255" placeholder="Collez l'adresse du profil (https://…)">
            <button type="submit" class="btn"><x-icon name="plus" /> Ajouter</button>
        </form>
        @error('url')<div class="error">{{ $message }}</div>@enderror
    </section>
@endcan

@can('update', $company)
    <section class="s-card">
        <h2 class="s-title"><x-icon name="notebook-pen" /> Ajouter une note</h2>
        <form method="POST" action="{{ route('pm.companies.activities.store', $company) }}" class="s-note">
            @csrf
            <input type="hidden" name="type" value="note">
            <label class="sr-only" for="body">Note</label>
            <textarea id="body" name="body" required maxlength="5000" rows="3" placeholder="Exemple : rappeler après 14 h, intéressé par un devis…"></textarea>
            <button type="submit" class="btn btn-primary">Enregistrer la note</button>
        </form>
    </section>
@endcan

<section class="s-card">
    <h2 class="s-title"><x-icon name="history" /> Historique</h2>
    <ul class="s-history">
        @forelse ($activities as $activity)
            <li><span class="s-when">{{ $activity->occurred_at->locale('fr')->isoFormat('D MMM') }}</span><span>{{ \Illuminate\Support\Str::limit($activity->body, 160) }}</span></li>
        @empty
            <li class="s-sub">Aucune activité pour l'instant.</li>
        @endforelse
    </ul>
</section>

<form method="POST" action="{{ route('pm.mode.toggle') }}" class="s-more">@csrf<button type="submit" class="btn-link small">Afficher tous les détails (mode complet)</button></form>
@endsection
