@extends('layouts.app')
@section('title', 'Modèles de messages sociaux')

@section('content')
<div class="page-header">
    <div>
        <div class="breadcrumb"><a href="{{ route('pm.social.index') }}">Réseaux sociaux</a></div>
        <h1>Modèles de messages</h1>
        <p class="subtitle">Textes courts proposés sur chaque fiche de suivi, prêts à copier puis à coller sur la plateforme. Rien n'est envoyé automatiquement.</p>
    </div>
    <div class="actions"><a class="btn btn-primary" href="{{ route('pm.social.templates.create') }}"><x-icon name="plus" /> Nouveau modèle</a></div>
</div>
<div class="card flush">
    @forelse ($templates as $template)
        <div class="settings-row">
            <div class="grow">
                <a class="row-title" href="{{ route('pm.social.templates.edit', $template) }}">{{ $template->name }}</a>
                <div class="sub">{{ $template->kindLabel() }} · {{ $template->platform ? config('prospection.social.platforms.'.$template->platform.'.label') : 'Tous les réseaux' }} · {{ mb_strlen($template->body) }} caractères</div>
                <div class="sub">{{ \Illuminate\Support\Str::limit($template->body, 140) }}</div>
            </div>
            @unless ($template->is_active)<span class="badge">Désactivé</span>@endunless
        </div>
    @empty
        <div class="empty-state"><x-icon name="copy" /><strong>Aucun modèle</strong>Créez une note d'invitation ou un premier message type pour gagner du temps.</div>
    @endforelse
</div>
@endsection
