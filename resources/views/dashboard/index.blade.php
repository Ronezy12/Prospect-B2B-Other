@extends('layouts.app')
@section('title', 'Ma journée')

@section('content')
<section class="hero">
    <div>
        <div class="eyebrow">{{ now()->locale('fr')->isoFormat('dddd D MMMM YYYY') }}</div>
        <h1>Bonjour {{ \Illuminate\Support\Str::before(auth()->user()->name, ' ') }}</h1>
        <p>
            @if ($myTasks->isNotEmpty())
                {{ $myTasks->count() }} chose(s) à faire aujourd'hui. Appelez, puis cliquez sur « Résultat » : l'outil s'occupe du reste.
            @elseif ($toCall->isNotEmpty())
                Rien de prévu aujourd'hui : voici des prospects à appeler en premier.
            @elseif ($totals['prospects'] === 0)
                Commencez par ajouter vos premiers prospects.
            @else
                Rien d'urgent aujourd'hui.
            @endif
        </p>
    </div>
</section>

<div class="big-actions">
    @can('prospects.create')
        <a class="big-action" href="{{ route('pm.companies.create') }}"><span class="big-icon"><x-icon name="plus" /></span><strong>Ajouter un prospect</strong><span>Une entreprise à démarcher</span></a>
    @endcan
    @can('campaigns.manage')
        <a class="big-action" href="{{ route('pm.simple-email.create') }}"><span class="big-icon"><x-icon name="mail" /></span><strong>Écrire à plusieurs entreprises</strong><span>Un e-mail en 3 étapes</span></a>
    @endcan
    <a class="big-action" href="{{ route('pm.pipelines.show') }}"><span class="big-icon"><x-icon name="kanban" /></span><strong>Où en sont mes affaires</strong><span>{{ $openOpportunities }} en cours</span></a>
    @can('prospects.import')
        <a class="big-action" href="{{ route('pm.imports.create') }}"><span class="big-icon"><x-icon name="upload" /></span><strong>Importer un fichier</strong><span>Excel ou CSV</span></a>
    @endcan
</div>

<section class="card flush section-gap" data-tour="today">
    <div class="card-head">
        <h2><x-icon name="alarm-clock" /> À faire aujourd'hui</h2>
        <a class="btn btn-sm" href="{{ route('pm.tasks.index') }}">Tout voir</a>
    </div>
    @forelse ($myTasks as $task)
        @include('tasks.row', ['task' => $task])
    @empty
        <div class="empty-state"><x-icon name="circle-check" /><strong>Rien de prévu aujourd'hui</strong>Les rappels se créent tout seuls quand vous cliquez sur un résultat.</div>
    @endforelse
    @if ($socialToDo)
        <div class="task-row">
            <div class="grow"><a class="t-title" href="{{ route('pm.social.index') }}">{{ $socialToDo }} profil(s) à contacter sur les réseaux sociaux</a></div>
            <a class="btn btn-sm" href="{{ route('pm.social.index') }}"><x-icon name="share-2" /> Y aller</a>
        </div>
    @endif
</section>

@if ($toCall->isNotEmpty())
    <section class="card flush section-gap" data-tour="tocall">
        <div class="card-head">
            <h2><x-icon name="phone" /> Prospects à appeler en premier</h2>
            <span class="small muted">Nouveaux, jamais contactés</span>
        </div>
        @foreach ($toCall as $company)
            @php($contact = $company->contacts->first())
            @php($phone = $contact?->mobile ?? $contact?->phone ?? $company->phone)
            <div class="task-row">
                <div class="grow">
                    <a class="t-title" href="{{ route('pm.companies.show', $company) }}">{{ $company->name }}</a>
                    <div class="t-meta">@if ($contact)<span>{{ $contact->fullName() }}@if ($contact->job_title) · {{ $contact->job_title }}@endif</span>@endif @if ($company->city)<span>{{ $company->city }}</span>@endif</div>
                </div>
                @if ($phone)<a class="btn btn-sm" href="tel:{{ $phone }}"><x-icon name="phone" /> <span class="hide-sm">{{ \App\Support\Normalizer::displayPhone($phone) }}</span></a>@endif
                @include('partials.outcomes', ['company' => $company, 'compact' => true])
            </div>
        @endforeach
    </section>
@endif

<section class="card section-gap">
    <h2><x-icon name="history" /> Dernières activités</h2>
    @forelse ($recentActivities as $activity)
        @if ($loop->first)<ul class="timeline">@endif
        <li>
            <div class="meta">{{ $activity->occurred_at->format('d/m à H:i') }} · {{ $activity->user?->name ?? 'Système' }}
                @if ($activity->subject) · <a href="{{ route('pm.companies.show', $activity->subject_id) }}">{{ $activity->subject->name }}</a>@endif
            </div>
            <div class="body">{{ \Illuminate\Support\Str::limit($activity->body, 110) }}</div>
        </li>
        @if ($loop->last)</ul>@endif
    @empty
        <div class="empty-state"><x-icon name="history" /><strong>Aucune activité</strong>Tout ce qui est fait apparaîtra ici.</div>
    @endforelse
</section>
@endsection
