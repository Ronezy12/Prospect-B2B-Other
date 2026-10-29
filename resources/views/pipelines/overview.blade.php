@extends('layouts.app')
@section('title', 'Vue globale des pipelines')

@section('content')
<div class="page-header">
    <div>
        <h1>Pipelines commerciales</h1>
        <p class="subtitle">Vue consolidée de toutes les pipelines de votre périmètre</p>
    </div>
</div>

@include('partials.pipeline-tabs')

<div class="stats">
    <div class="stat"><span class="stat-icon"><x-icon name="briefcase" /></span><div><div class="value">{{ $totals['open'] }}</div><div class="label">Opportunités en cours</div></div></div>
    <div class="stat"><span class="stat-icon green"><x-icon name="circle-check" /></span><div><div class="value">{{ $totals['won'] }}</div><div class="label">Gagnées</div></div></div>
    <div class="stat"><span class="stat-icon pink"><x-icon name="x" /></span><div><div class="value">{{ $totals['lost'] }}</div><div class="label">Perdues</div></div></div>
    <div class="stat"><span class="stat-icon orange"><x-icon name="alarm-clock" /></span><div><div class="value">{{ $totals['overdue'] }}</div><div class="label">Relances en retard</div></div></div>
    <div class="stat"><span class="stat-icon blue"><x-icon name="trending-up" /></span><div><div class="value">{{ number_format($totals['weighted'], 0, ',', ' ') }} €</div><div class="label">Montant pondéré en cours</div></div></div>
</div>

@foreach ($pipelines as $pipeline)
    @php($rows = ($stats[$pipeline->id] ?? collect())->keyBy('stage_id'))
    @php($total = $rows->sum('n'))
    <section class="card flush">
        <div class="card-head">
            <h2><a href="{{ route('pm.pipelines.show', $pipeline) }}">{{ $pipeline->name }}</a> <span class="muted">· {{ $total }} opportunité(s)</span></h2>
            <a class="btn btn-sm" href="{{ route('pm.pipelines.show', $pipeline) }}"><x-icon name="kanban" /> Ouvrir le Kanban</a>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr>@foreach ($pipeline->stages as $stage)<th>{{ $stage->name }}</th>@endforeach</tr></thead>
                <tbody>
                <tr>
                    @foreach ($pipeline->stages as $stage)
                        @php($r = $rows[$stage->id] ?? null)
                        <td class="num">
                            <a href="{{ route('pm.opportunities.index', ['pipeline_id' => $pipeline->id, 'stage_id' => $stage->id, 'state' => 'all']) }}"><strong>{{ $r->n ?? 0 }}</strong></a>
                            @if ($r && $r->total)<div class="sub">{{ number_format((float) $r->total, 0, ',', ' ') }} €</div>@endif
                        </td>
                    @endforeach
                </tr>
                </tbody>
            </table>
        </div>
    </section>
@endforeach
@endsection
