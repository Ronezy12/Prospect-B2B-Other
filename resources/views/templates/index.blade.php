@extends('layouts.app')
@section('title', 'Modèles d\'e-mails')

@section('content')
<div class="page-header">
    <div>
        <h1>Modèles d'e-mails</h1>
        <p class="subtitle">Chaque enregistrement crée une version : une campagne garde toujours le contenu exact qu'elle a envoyé.</p>
    </div>
    @can('templates.manage')<div class="actions"><a class="btn btn-primary" href="{{ route('pm.templates.create') }}"><x-icon name="plus" /> Nouveau modèle</a></div>@endcan
</div>

<div class="card flush">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Modèle</th><th>Catégorie</th><th>Objet</th><th>Version</th><th>Modifié</th><th></th></tr></thead>
            <tbody>
            @forelse ($templates as $template)
                <tr>
                    <td><a class="row-title" href="{{ route('pm.templates.show', $template) }}">{{ $template->name }}</a></td>
                    <td><span class="badge plain">{{ \App\Http\Controllers\TemplateController::CATEGORIES[$template->category] ?? $template->category }}</span></td>
                    <td class="small">{{ \Illuminate\Support\Str::limit($template->latestVersion?->subject, 70) }}</td>
                    <td class="num">v{{ $template->current_version }}</td>
                    <td class="muted nowrap">{{ $template->updated_at->format('d/m/Y') }}</td>
                    <td class="nowrap">@can('templates.manage')<a class="btn btn-sm" href="{{ route('pm.templates.edit', $template) }}"><x-icon name="pencil" /> Éditer</a>@endcan</td>
                </tr>
            @empty
                <tr><td colspan="6" class="table-empty"><div class="empty-state"><x-icon name="layers" /><strong>Aucun modèle</strong>Créez votre premier modèle à partir d'une base prête à l'emploi.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $templates->links('partials.pagination') }}
</div>
@endsection
