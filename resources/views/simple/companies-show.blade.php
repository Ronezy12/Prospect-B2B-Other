@extends('layouts.app')
@section('title', $company->name)

@section('content')
@php($contact = $company->contacts->first())
@php($phone = $contact?->mobile ?? $contact?->phone ?? $company->phone)
@php($email = $contact?->email ?? $company->email)
<a class="s-back" href="{{ route('pm.companies.index') }}">‹ Mes prospects</a>

<section class="s-card s-profile">
    <h1>{{ $company->name }}</h1>
    <div class="s-sub">{{ $contact?->fullName() ?? 'Personne à contacter non renseignée' }}@if ($contact?->job_title) · {{ $contact->job_title }}@endif @if ($company->city) · {{ $company->city }}@endif</div>
    <span class="s-chip s-chip-{{ $company->status }}">{{ $company->isClient() ? 'Client' : $company->statusLabel() }}</span>
    <div class="s-actions">
        @if ($phone)<a class="s-call" href="tel:{{ $phone }}">📞 <span>Appeler {{ \App\Support\Normalizer::displayPhone($phone) }}</span></a>@endif
        @if ($email)
            @can('emails.individual')
                @if ($contact && $contact->email)<a class="s-mail" href="{{ route('pm.contacts.email.create', $contact) }}">✉️ <span>Envoyer un e-mail</span></a>@endif
            @else
                <span class="s-sub">✉️ {{ $email }}</span>
            @endcan
        @endif
    </div>
    @if ($nextTask)
        <p class="s-next">⏰ Prochain rappel : <strong>{{ $nextTask->title }}</strong>, {{ $nextTask->due_at->locale('fr')->isoFormat('dddd D MMMM') }}</p>
    @endif
</section>

@unless ($company->isClient())
    <section class="s-card">
        <h2 class="s-title">Vous venez de l'appeler ? Comment ça s'est passé ?</h2>
        @include('simple.outcomes', ['company' => $company])
        <p class="s-help">L'outil note tout et crée le prochain rappel tout seul.</p>
    </section>
@endunless

@can('update', $company)
    <section class="s-card">
        <h2 class="s-title">📝 Écrire une note</h2>
        <form method="POST" action="{{ route('pm.companies.activities.store', $company) }}" class="s-note">
            @csrf
            <input type="hidden" name="type" value="note">
            <label class="sr-only" for="body">Note</label>
            <textarea id="body" name="body" required maxlength="5000" rows="3" placeholder="Ex. : rappeler après 14 h, intéressé par un devis…"></textarea>
            <button type="submit" class="btn btn-primary">Enregistrer la note</button>
        </form>
    </section>
@endcan

<section class="s-card">
    <h2 class="s-title">🕘 Ce qui s'est passé</h2>
    <ul class="s-history">
        @forelse ($activities as $activity)
            <li><span class="s-when">{{ $activity->occurred_at->locale('fr')->isoFormat('D MMM') }}</span> {{ \Illuminate\Support\Str::limit($activity->body, 140) }}</li>
        @empty
            <li class="s-sub">Rien pour l'instant.</li>
        @endforelse
    </ul>
</section>

<form method="POST" action="{{ route('pm.mode.toggle') }}" class="s-more">@csrf<button type="submit" class="btn-link small">Voir tous les détails (mode complet)</button></form>
@endsection
