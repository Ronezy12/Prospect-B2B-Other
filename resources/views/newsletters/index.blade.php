@extends('layouts.app')
@section('title', 'Newsletters')

@section('content')
<div class="page-header">
    <div>
        <h1>Newsletters</h1>
        <p class="subtitle">Listes d'abonnés avec preuve de consentement. Les envois se préparent dans Campagnes, type « Newsletter ».</p>
    </div>
    @can('campaigns.manage')<div class="actions"><a class="btn btn-primary" href="{{ route('pm.campaigns.create', ['type' => 'newsletter']) }}"><x-icon name="mail" /> Nouvelle newsletter</a></div>@endcan
</div>

<div class="grid grid-sidebar">
    <section class="card flush">
        <div class="card-head"><h2>Listes</h2></div>
        @forelse ($lists as $list)
            <div class="settings-row">
                <div class="grow"><a class="row-title" href="{{ route('pm.newsletters.show', $list) }}">{{ $list->name }}</a><div class="sub">{{ $list->description }}</div></div>
                <span class="badge badge-success">{{ $list->subscribed_count }} abonné(s)</span>
                <span class="badge plain">{{ $list->unsubscribed_count }} désabonné(s)</span>
            </div>
        @empty
            <div class="empty-state"><x-icon name="newspaper" /><strong>Aucune liste</strong>Créez une liste puis ajoutez les personnes qui ont donné leur accord.</div>
        @endforelse
    </section>
    @can('campaigns.manage')
        <form method="POST" action="{{ route('pm.newsletters.store') }}" class="card">
            @csrf
            <h2>Nouvelle liste</h2>
            <div class="field"><label for="name">Nom *</label><input type="text" id="name" name="name" required maxlength="150" placeholder="ex. Lettre mensuelle syndics"></div>
            <div class="field section-gap"><label for="description">Description</label><input type="text" id="description" name="description" maxlength="255"></div>
            <div class="form-actions"><button type="submit" class="btn btn-primary"><x-icon name="plus" /> Créer</button></div>
        </form>
    @endcan
</div>
@endsection
