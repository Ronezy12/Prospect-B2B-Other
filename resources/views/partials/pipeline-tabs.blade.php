<nav class="tabs" aria-label="Pipelines">
    <a href="{{ route('pm.pipelines.overview') }}" @class(['tab', 'active' => request()->routeIs('pm.pipelines.overview')])><x-icon name="layers" /> Vue globale</a>
    <a href="{{ route('pm.opportunities.index') }}" @class(['tab', 'active' => request()->routeIs('pm.opportunities.index')])><x-icon name="list-checks" /> Liste</a>
    @foreach ($pipelines as $p)
        <a href="{{ route('pm.pipelines.show', $p) }}" @class(['tab', 'active' => isset($pipeline) && request()->routeIs('pm.pipelines.show') && $pipeline->id === $p->id])>{{ $p->name }}</a>
    @endforeach
</nav>
