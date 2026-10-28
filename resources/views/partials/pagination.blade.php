@if ($paginator->hasPages() || $paginator->total() > 0)
    <nav aria-label="Pagination">
        <ul class="pagination">
            @if ($paginator->onFirstPage())
                <li class="disabled"><span>Précédent</span></li>
            @else
                <li><a href="{{ $paginator->previousPageUrl() }}" rel="prev">Précédent</a></li>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li class="disabled"><span>{{ $element }}</span></li>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li class="active" aria-current="page"><span>{{ $page }}</span></li>
                        @else
                            <li><a href="{{ $url }}">{{ $page }}</a></li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <li><a href="{{ $paginator->nextPageUrl() }}" rel="next">Suivant</a></li>
            @else
                <li class="disabled"><span>Suivant</span></li>
            @endif
            <li class="summary">{{ $paginator->firstItem() ?? 0 }}–{{ $paginator->lastItem() ?? 0 }} sur {{ number_format($paginator->total(), 0, ',', ' ') }}</li>
        </ul>
    </nav>
@endif
