@extends('layouts.app')
@section('title', $template->name)

@section('content')
<div class="page-header">
    <div>
        <div class="breadcrumb"><a href="{{ route('pm.templates.index') }}">Modèles d'e-mails</a></div>
        <h1>{{ $template->name }}</h1>
        <p class="subtitle">Version {{ $version->version }} · {{ \App\Http\Controllers\TemplateController::CATEGORIES[$template->category] ?? $template->category }} · objet : « {{ $version->subject }} »</p>
    </div>
    <div class="actions">
        @can('templates.manage')
            <a class="btn btn-primary" href="{{ route('pm.templates.edit', $template) }}"><x-icon name="pencil" /> Éditer</a>
            <form method="POST" action="{{ route('pm.templates.destroy', $template) }}" data-confirm="Archiver ce modèle ?">@csrf @method('DELETE')<button type="submit" class="btn btn-danger"><x-icon name="trash-2" /> Archiver</button></form>
        @endcan
    </div>
</div>

<div class="grid grid-sidebar">
    <div class="stack">
        <section class="card">
            <div class="toolbar">
                <h2 class="grow">Aperçu <span class="muted small">(données d'exemple)</span></h2>
                <span class="badge plain">Ordinateur 640 px</span><span class="badge plain">Mobile 375 px</span>
            </div>
            <div class="preview-pair">
                <iframe class="preview-desktop" src="{{ route('pm.templates.preview', [$template, 'version' => $version->version]) }}" sandbox title="Aperçu ordinateur" loading="lazy"></iframe>
                <iframe class="preview-mobile" src="{{ route('pm.templates.preview', [$template, 'version' => $version->version]) }}" sandbox title="Aperçu mobile" loading="lazy"></iframe>
            </div>
        </section>
    </div>
    <div class="stack">
        @can('templates.manage')
            <section class="card">
                <h2><x-icon name="mail" /> Envoyer un test</h2>
                @if ($accounts->isEmpty())
                    <p class="small muted">Aucun compte d'envoi actif. @can('settings.manage')<a href="{{ route('pm.settings.mail.create') }}">Configurer un compte</a>@endcan</p>
                @else
                    <form method="POST" action="{{ route('pm.templates.test', [$template, 'version' => $version->version]) }}">
                        @csrf
                        <div class="field"><label for="mail_account_id">Compte d'envoi</label><select id="mail_account_id" name="mail_account_id">@foreach ($accounts as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach</select></div>
                        <div class="field section-gap"><label for="emails">Destinataires (5 max)</label><input type="text" id="emails" name="emails" value="{{ old('emails', auth()->user()->email) }}" required>@error('emails')<div class="error">{{ $message }}</div>@enderror</div>
                        <div class="form-actions"><button type="submit" class="btn btn-primary"><x-icon name="mail" /> Envoyer le test</button></div>
                    </form>
                @endif
            </section>
        @endcan
        <section class="card">
            <h2><x-icon name="history" /> Versions</h2>
            <ul class="timeline">
                @foreach ($versions as $v)
                    <li>
                        <div class="meta">v{{ $v->version }} · {{ $v->created_at->format('d/m/Y H:i') }} · {{ $v->author?->name ?? '—' }}</div>
                        <div class="body">@if ($v->version === $version->version)<strong>{{ $v->subject }}</strong> (affichée)@else<a href="{{ route('pm.templates.show', [$template, 'version' => $v->version]) }}">{{ $v->subject }}</a>@endif</div>
                    </li>
                @endforeach
            </ul>
        </section>
    </div>
</div>
@endsection
