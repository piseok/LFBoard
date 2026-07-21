@if ($paginator->hasPages())
    <nav aria-label="{{ __('페이지 이동') }}">
        <ul class="pagination">
            @if ($paginator->onFirstPage())
                <li class="disabled" aria-hidden="true"><span>&laquo;&laquo;</span></li>
                <li class="disabled" aria-hidden="true"><span>&laquo;</span></li>
            @else
                <li><a href="{{ $paginator->url(1) }}" aria-label="{{ __('첫 페이지') }}">&laquo;&laquo;</a></li>
                <li><a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('이전 페이지') }}">&laquo;</a></li>
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
                <li><a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('다음 페이지') }}">&raquo;</a></li>
                <li><a href="{{ $paginator->url($paginator->lastPage()) }}" aria-label="{{ __('마지막 페이지') }}">&raquo;&raquo;</a></li>
            @else
                <li class="disabled" aria-hidden="true"><span>&raquo;</span></li>
                <li class="disabled" aria-hidden="true"><span>&raquo;&raquo;</span></li>
            @endif
        </ul>
    </nav>
@endif
