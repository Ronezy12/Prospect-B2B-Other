@extends('layouts.app')
@section('title', $company->name)

@section('content')
@php($contact = $company->contacts->first())
@php($phone = $contact?->mobile ?? $contact?->phone ?? $company->phone)
@php($mobile = $contact?->mobile ?? $phone)
@php($email = $contact?->email ?? $company->email)
<a class="s-back" href="{{ route('pm.companies.index') }}"><x-icon name="chevron-right" class="flip" /> Mes prospects</a>

<div class="c360">
    {{-- Colonne gauche : qui est ce prospect --}}
    <aside class="c360-side">
        <section class="pc-card c360-id pc-{{ $company->isClient() ? 'qualifie' : $company->status }}">
            <div class="pc-top">
                <h1 class="pc-agency">{{ $company->name }}</h1>
                <span class="pc-person">{{ $contact?->fullName() ?? '—' }}</span>
            </div>
            @if ($contact?->job_title)<div class="s-sub">{{ $contact->job_title }}</div>@endif
            <div class="pc-city"><x-icon name="map-pin" /> {{ $company->city ?: 'Ville non renseignée' }}</div>
            <div class="pc-status">{{ $company->isClient() ? 'Client' : $company->statusLabel() }}</div>
            <div class="pc-actions">
                @if ($contact?->email && auth()->user()->can('emails.individual'))
                    <a class="pc-btn" href="{{ route('pm.contacts.email.create', $contact) }}" title="Envoyer un e-mail" aria-label="Envoyer un e-mail"><x-icon name="mail" /></a>
                @elseif ($email)
                    <a class="pc-btn" href="mailto:{{ $email }}" title="Écrire à {{ $email }}" aria-label="Écrire à {{ $email }}"><x-icon name="mail" /></a>
                @else
                    <span class="pc-btn off" title="Pas d'e-mail renseigné"><x-icon name="mail" /></span>
                @endif
                @if ($phone)
                    <a class="pc-btn call" href="tel:{{ $phone }}" title="Appeler {{ \App\Support\Normalizer::displayPhone($phone) }}" aria-label="Appeler"><x-icon name="phone" /></a>
                @else
                    <span class="pc-btn off" title="Pas de téléphone renseigné"><x-icon name="phone" /></span>
                @endif
                @if ($mobile)
                    <a class="pc-btn" href="sms:{{ $mobile }}" title="Envoyer un SMS" aria-label="Envoyer un SMS"><x-icon name="message-square-more" /></a>
                @else
                    <span class="pc-btn off" title="Pas de numéro pour un SMS"><x-icon name="message-square-more" /></span>
                @endif
            </div>
            @if ($phone || $email)
                <dl class="c360-coords">
                    @if ($phone)<dt>Téléphone</dt><dd>{{ \App\Support\Normalizer::displayPhone($phone) }}</dd>@endif
                    @if ($email)<dt>E-mail</dt><dd class="break">{{ $email }}</dd>@endif
                </dl>
            @endif
        </section>

        @if ($nextTask)
            <p class="s-next"><x-icon name="alarm-clock" /> Prochain rappel : <strong>{{ $nextTask->title }}</strong>, {{ $nextTask->due_at->locale('fr')->isoFormat('dddd D MMMM') }}</p>
        @endif

        <section class="s-card">
            <h2 class="s-title"><x-icon name="users-round" /> Interlocuteurs</h2>
            @forelse ($company->contacts as $c)
                <div class="s-line">
                    <x-avatar :name="$c->fullName()" size="sm" />
                    <span class="s-line-main">{{ $c->fullName() }}@if ($c->job_title)<span class="s-sub"> · {{ $c->job_title }}</span>@endif</span>
                    @if ($c->mobile ?? $c->phone)<a class="pc-btn mini call" href="tel:{{ $c->mobile ?? $c->phone }}" aria-label="Appeler {{ $c->fullName() }}"><x-icon name="phone" /></a>@endif
                    @if ($c->email)<a class="pc-btn mini" href="mailto:{{ $c->email }}" aria-label="Écrire à {{ $c->fullName() }}"><x-icon name="mail" /></a>@endif
                </div>
            @empty
                <p class="s-sub">Aucun interlocuteur.</p>
            @endforelse
            @can('update', $company)<a class="btn btn-sm section-gap" href="{{ route('pm.contacts.create', ['company_id' => $company->id]) }}"><x-icon name="user-plus" /> Ajouter un interlocuteur</a>@endcan
        </section>

        @can('social.use')
            <section class="s-card">
                <h2 class="s-title"><x-icon name="share-2" /> Réseaux sociaux</h2>
                @forelse ($company->socialProfiles as $profile)
                    <div class="s-line">
                        @include('social.partials.platform', ['platform' => $profile->platform])
                        <span class="s-line-main">{{ $profile->contact?->fullName() ?? 'Page' }} <span class="s-sub">· {{ $profile->statusLabel() }}</span></span>
                        @unless ($profile->isOpposed())<a class="pc-btn mini" href="{{ $profile->url }}" target="_blank" rel="noopener noreferrer nofollow" aria-label="Ouvrir le profil"><x-icon name="external-link" /></a>@endunless
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
                    <input type="url" id="sp-url" name="url" required maxlength="255" placeholder="Adresse du profil (https://…)">
                    <button type="submit" class="btn btn-sm"><x-icon name="plus" /> Ajouter</button>
                </form>
                @error('url')<div class="error">{{ $message }}</div>@enderror
            </section>
        @endcan
    </aside>

    {{-- Colonne droite : agir et suivre --}}
    <div class="c360-main">
        @unless ($company->isClient())
            <section class="s-card">
                <h2 class="s-title">Résultat de votre dernier appel</h2>
                @include('simple.outcomes', ['company' => $company])
                <p class="s-help">Le statut, l'affaire et le prochain rappel sont mis à jour automatiquement.</p>
            </section>
        @endunless

        <div class="c360-duo">
            <section class="s-card">
                <h2 class="s-title"><x-icon name="briefcase" /> Affaires</h2>
                @forelse ($deals as $deal)
                    <div class="s-line">
                        <span class="s-line-main">{{ $deal->title }}@if ($deal->amount)<span class="s-sub"> · {{ number_format((float) $deal->amount, 0, ',', ' ') }} €</span>@endif</span>
                        <span @class(['s-chip', 's-chip-qualifie' => $deal->stage?->type === 'won', 's-chip-non_pertinent' => $deal->stage?->type === 'lost'])>{{ $deal->stage?->name }}</span>
                    </div>
                @empty
                    <p class="s-sub">Aucune affaire : elle se crée toute seule quand le prospect est intéressé.</p>
                @endforelse
            </section>
            <section class="s-card">
                <h2 class="s-title"><x-icon name="list-checks" /> Rappels</h2>
                @forelse ($openTasks as $task)
                    <div class="s-line">
                        <span class="s-line-main">{{ \Illuminate\Support\Str::limit($task->title, 60) }}</span>
                        <span @class(['s-sub', 'danger' => $task->isOverdue()])>{{ $task->due_at->format('d/m') }}</span>
                    </div>
                @empty
                    <p class="s-sub">Aucun rappel prévu.</p>
                @endforelse
            </section>
        </div>

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
                    <li><span class="s-when">{{ $activity->occurred_at->locale('fr')->isoFormat('D MMM') }}</span><span>{{ \Illuminate\Support\Str::limit($activity->body, 180) }}@if ($activity->user)<span class="s-sub"> — {{ $activity->user->name }}</span>@endif</span></li>
                @empty
                    <li class="s-sub">Aucune activité pour l'instant.</li>
                @endforelse
            </ul>
        </section>

        <form method="POST" action="{{ route('pm.mode.toggle') }}" class="s-more">@csrf<button type="submit" class="btn-link small">Afficher tous les détails (mode complet)</button></form>
    </div>
</div>
@endsection
