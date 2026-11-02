@extends('layouts.app')
@section('title', 'Liste d\'opposition')

@section('content')
<div class="page-header">
    <div>
        <h1>Liste d'opposition</h1>
        <p class="subtitle">Les personnes qui ne veulent plus être contactées. Les adresses ne sont pas stockées en clair : pour retrouver quelqu'un, tapez son adresse.</p>
    </div>
</div>

<div class="privacy-summary">
    @foreach (config('prospection.suppression_reasons') as $key => $label)
        <div><strong>{{ $byReason[$key] ?? 0 }}</strong><span>{{ $label }}</span></div>
    @endforeach
    <div><strong>{{ $socialCount }}</strong><span>Profils sociaux opposés</span></div>
</div>

<section class="card section-gap">
    <h2><x-icon name="search" /> Vérifier une adresse</h2>
    <form method="GET" class="toolbar">
        <label class="sr-only" for="email">Adresse e-mail</label>
        <input type="email" id="email" name="email" value="{{ $email }}" placeholder="adresse@exemple.fr" required>
        <button type="submit" class="btn btn-primary">Vérifier</button>
    </form>
    @if ($email)
        @forelse ($matches as $s)
            <div class="task-row">
                <div class="grow">
                    <div class="t-title">{{ config('prospection.suppression_reasons')[$s->reason] ?? $s->reason }} — {{ config('prospection.suppression_scopes')[$s->scope] ?? $s->scope }}</div>
                    <div class="t-meta"><span>depuis le {{ $s->created_at->format('d/m/Y') }}</span><span>origine : {{ str_replace('_', ' ', $s->origin) }}</span>@if ($s->author)<span>par {{ $s->author->name }}</span>@endif @if ($s->note)<span>{{ $s->note }}</span>@endif</div>
                </div>
                <details class="outcome-menu">
                    <summary class="btn btn-sm">Lever</summary>
                    <div class="outcome-pop">
                        <p class="small">À faire uniquement si la personne a donné un nouvel accord écrit, ou en cas d'erreur de saisie. La décision est tracée.</p>
                        @if (in_array($s->reason, ['rebond_definitif', 'adresse_invalide'], true))<p class="small error">Adresse signalée invalide par le serveur : la lever peut nuire à la délivrabilité.</p>@endif
                        <form method="POST" action="{{ route('pm.compliance.suppressions.lift', $s) }}">
                            @csrf
                            <div class="field"><label for="j-{{ $s->id }}">Justification *</label><textarea id="j-{{ $s->id }}" name="justification" minlength="15" maxlength="1000" required placeholder="ex. nouveau consentement écrit reçu le 02/11 par e-mail"></textarea></div>
                            <label class="checkbox"><input type="checkbox" name="confirm" value="1" required> Je confirme la levée de cette opposition</label>
                            <div class="form-actions"><button type="submit" class="btn btn-sm btn-danger">Lever l'opposition</button></div>
                        </form>
                    </div>
                </details>
            </div>
        @empty
            <p class="muted">Aucune opposition pour {{ $email }} : cette adresse peut être contactée (selon sa base légale).</p>
        @endforelse
    @endif
</section>

<div class="grid grid-2 section-gap">
    <section class="card">
        <h2>Dernières oppositions</h2>
        <ul class="timeline">
            @forelse ($recent as $s)
                <li><div class="meta">{{ $s->created_at->format('d/m/Y H:i') }} · {{ str_replace('_', ' ', $s->origin) }}@if ($s->author) · {{ $s->author->name }}@endif</div><div class="body small">{{ config('prospection.suppression_reasons')[$s->reason] ?? $s->reason }} — {{ config('prospection.suppression_scopes')[$s->scope] ?? $s->scope }}</div></li>
            @empty
                <li class="muted">Aucune opposition.</li>
            @endforelse
        </ul>
    </section>
    <form method="POST" action="{{ route('pm.compliance.social.lift') }}" class="card">
        @csrf
        <h2>Lever une opposition sur un réseau social</h2>
        <div class="field"><label for="s-platform">Réseau</label><select id="s-platform" name="platform">@foreach (config('prospection.social.platforms') as $k => $p)<option value="{{ $k }}">{{ $p['label'] }}</option>@endforeach</select></div>
        <div class="field"><label for="s-url">Adresse du profil</label><input type="text" id="s-url" name="url" required maxlength="255">@error('url')<div class="error">{{ $message }}</div>@enderror</div>
        <div class="field"><label for="s-just">Justification *</label><textarea id="s-just" name="justification" minlength="15" maxlength="1000" required></textarea></div>
        <div class="form-actions"><button type="submit" class="btn btn-danger btn-sm">Lever</button></div>
    </form>
</div>
@endsection
