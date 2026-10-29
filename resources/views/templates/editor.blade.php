@extends('layouts.app')
@section('title', 'Éditer '.$template->name)

@push('head')
    <link rel="stylesheet" href="{{ asset('vendor/grapesjs/grapes.min.css') }}">
    <script src="{{ asset('vendor/grapesjs/grapes.min.js') }}" defer></script>
    <script src="{{ asset('vendor/grapesjs/grapesjs-preset-newsletter.min.js') }}" defer></script>
    <script src="{{ asset('vendor/grapesjs/locale-fr.js') }}" defer></script>
    <script src="{{ asset('js/editor.js') }}?v=2" defer></script>
@endpush

@section('content')
<div class="page-header">
    <div>
        <div class="breadcrumb"><a href="{{ route('pm.templates.index') }}">Modèles d'e-mails</a> <x-icon name="chevron-right" /> <a href="{{ route('pm.templates.show', $template) }}">{{ $template->name }}</a></div>
        <h1>Éditeur · v{{ $template->current_version }}</h1>
    </div>
    <div class="actions">
        <a class="btn" href="{{ route('pm.templates.show', $template) }}"><x-icon name="search" /> Aperçu et test</a>
    </div>
</div>

<form method="POST" action="{{ route('pm.templates.update', $template) }}" class="card" data-editor-form>
    @csrf @method('PUT')
    <div class="form-grid">
        <div class="field"><label for="name">Nom du modèle *</label><input type="text" id="name" name="name" value="{{ old('name', $template->name) }}" required maxlength="150"></div>
        <div class="field"><label for="subject">Objet *</label><input type="text" id="subject" name="subject" value="{{ old('subject', $version?->subject) }}" required maxlength="190">@error('subject')<div class="error">{{ $message }}</div>@enderror</div>
        <div class="field full"><label for="preheader">Texte d'aperçu</label><input type="text" id="preheader" name="preheader" value="{{ old('preheader', $version?->preheader) }}" maxlength="190" placeholder="Phrase affichée après l'objet dans la boîte de réception"></div>
    </div>
    <p class="small muted section-gap">
        Variables disponibles (bloc « Variables » de l'éditeur, ou à taper telles quelles) :
        @foreach ($variables as $key => $label)<code title="{{ $label }}">&#123;&#123;{{ $key }}&#125;&#125;</code> @endforeach
    </p>
    <textarea name="html" hidden data-editor-html>{{ old('html', $version?->html) }}</textarea>
    <textarea name="design" hidden data-editor-design>{{ old('design', $version?->design) }}</textarea>
    <div id="gjs" class="gjs-host" data-editor></div>
    <noscript><div class="alert alert-warning">L'éditeur visuel nécessite JavaScript.</div></noscript>
    <div class="form-actions">
        <span class="small muted grow">L'enregistrement crée la version {{ $template->current_version + 1 }} ; les versions précédentes restent consultables.</span>
        <button type="submit" class="btn btn-primary"><x-icon name="check" /> Enregistrer une nouvelle version</button>
    </div>
</form>
@endsection
