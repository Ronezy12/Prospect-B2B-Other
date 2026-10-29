@extends('layouts.app')
@section('title', 'Segments')

@section('content')
<div class="page-header">
    <div>
        <h1>Segments</h1>
        <p class="subtitle">Groupes de destinataires réutilisables, calculés au moment de l'envoi.</p>
    </div>
    @can('campaigns.manage')<div class="actions"><a class="btn btn-primary" href="{{ route('pm.segments.create') }}"><x-icon name="plus" /> Nouveau segment</a></div>@endcan
</div>
<div class="card flush">
    @forelse ($segments as $segment)
        <div class="settings-row">
            <div class="grow">
                <a class="row-title" href="{{ route('pm.segments.edit', $segment) }}">{{ $segment->name }}</a>
                <div class="sub">{{ count($segment->filters) }} critère(s) · modifié le {{ $segment->updated_at->format('d/m/Y') }}</div>
            </div>
        </div>
    @empty
        <div class="empty-state"><x-icon name="filter" /><strong>Aucun segment</strong>Définissez qui recevra vos campagnes : secteur, ville, statut, tags…</div>
    @endforelse
    {{ $segments->links('partials.pagination') }}
</div>
@endsection
