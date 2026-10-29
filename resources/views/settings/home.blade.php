@extends('layouts.app')
@section('title', 'Paramètres')

@section('content')
<div class="page-header">
    <div><h1>Paramètres</h1><p class="subtitle">Tout ce qui se règle une fois, au démarrage.</p></div>
</div>
<div class="tile-grid">
    @foreach ($items as $item)
        <a class="tile" href="{{ route($item['route']) }}">
            <span class="stat-icon"><x-icon :name="$item['icon']" /></span>
            <span class="tile-body">
                <strong>{{ $item['title'] }}</strong>
                <span class="muted small">{{ $item['text'] }}</span>
                <span @class(['badge', 'badge-success' => $item['ok'] && empty($item['optional']), 'badge-warning' => ! $item['ok'] && empty($item['optional'])])>{{ $item['state'] }}</span>
            </span>
            <x-icon name="chevron-right" class="tile-arrow" />
        </a>
    @endforeach
</div>
@endsection
