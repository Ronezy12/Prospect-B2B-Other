@extends('layouts.app')
@section('title', 'Écrire à plusieurs entreprises')

@section('content')
<div class="page-header">
    <div><h1>Écrire à plusieurs entreprises</h1><p class="subtitle">Dernière vérification avant l'envoi.</p></div>
</div>
@include('simple-email.steps', ['current' => 3])

<div class="grid grid-sidebar">
    <section class="card">
        <h2>Aperçu @if ($preview['to'])<span class="muted small">— pour {{ $preview['to'] }}</span>@endif</h2>
        <div class="mail-preview">
            <div class="mail-subject"><span class="muted small">Objet :</span> {{ $preview['subject'] }}</div>
            <div class="mail-body pre">{{ $preview['body'] }}</div>
            <div class="mail-foot small muted">Se désinscrire</div>
        </div>
        <div class="form-actions">
            <a class="btn" href="{{ route('pm.simple-email.message') }}"><x-icon name="pencil" /> Modifier le message</a>
            <form method="POST" action="{{ route('pm.simple-email.test') }}">@csrf<button type="submit" class="btn"><x-icon name="mail" /> M'envoyer un test</button></form>
        </div>
    </section>

    <aside class="card wizard-count">
        <div class="count-big">{{ $estimate['eligible'] }}</div>
        <div class="count-label">personne(s) vont le recevoir</div>
        @include('simple-email.removed')
        @if ($account?->driver === 'log')<p class="small muted">Mode démonstration : les e-mails sont simulés, rien ne part réellement.</p>@endif
        @unless ($tested)<p class="small muted">Conseil : envoyez-vous un test avant.</p>@endunless

        <form method="POST" action="{{ route('pm.simple-email.send') }}" class="section-gap">
            @csrf
            <label class="checkbox"><input type="checkbox" name="confirm" value="1" required> Ce sont des professionnels que mon entreprise peut démarcher.</label>
            @error('confirm')<div class="error">{{ $message }}</div>@enderror
            <fieldset class="when">
                <legend class="sr-only">Quand ?</legend>
                <label class="checkbox"><input type="radio" name="when" value="now" checked> Envoyer maintenant</label>
                <label class="checkbox"><input type="radio" name="when" value="later"> Envoyer le</label>
                <input type="datetime-local" name="scheduled_at" aria-label="Date d'envoi" value="{{ now()->addDay()->setTime(9, 30)->format('Y-m-d\TH:i') }}">
                @error('scheduled_at')<div class="error">{{ $message }}</div>@enderror
            </fieldset>
            <button type="submit" class="btn btn-primary btn-block"><x-icon name="send" /> {{ $canSend ? 'Envoyer' : 'Préparer (validation par un responsable)' }}</button>
        </form>
        <form method="POST" action="{{ route('pm.simple-email.reset') }}">@csrf<button type="submit" class="btn-link small">Tout recommencer</button></form>
    </aside>
</div>
@endsection
