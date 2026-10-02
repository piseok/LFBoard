@if ($paginator->hasPages())
    <nav class="pagination_wrap" aria-label="{{ __('페이지 이동') }}">
        <div class="pagination">
            <div class="button_box">
                @if ($paginator->onFirstPage())
                    <div class="disabled prev depth" aria-hidden="true">
                        <span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 14 14" fill="none">
                            <path d="M6.75 0.750001L0.749999 6.75L6.75 12.75" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M12.75 0.750001L6.75 6.75L12.75 12.75" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                    </div>
                    <div class="disabled prev" aria-hidden="true">
                        <span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="7" height="12" viewBox="0 0 7 12" fill="none">
                            <path d="M6.74651 0.987617L2.00561 6.06566L6.86535 10.9816L5.8358 11.9991L-0.000461927 6.0946L5.68898 4.67536e-06L6.74651 0.987617Z" fill="currentColor"/>
                            </svg>
                        </span>
                    </div>
                @else
                    <div class="prev depth">
                        <a href="{{ $paginator->url(1) }}" aria-label="{{ __('첫 페이지') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 14 14" fill="none">
                            <path d="M6.75 0.750001L0.749999 6.75L6.75 12.75" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M12.75 0.750001L6.75 6.75L12.75 12.75" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </a>
                    </div>
                    <div class="prev">
                        <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('이전 페이지') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="7" height="12" viewBox="0 0 7 12" fill="none">
                            <path d="M6.74651 0.987617L2.00561 6.06566L6.86535 10.9816L5.8358 11.9991L-0.000461927 6.0946L5.68898 4.67536e-06L6.74651 0.987617Z" fill="currentColor"/>
                            </svg>
                        </a>
                    </div>
                @endif
            </div>

            @php
                $currentPage = $paginator->currentPage();
                $lastPage = $paginator->lastPage();

                /**
                * 표시할 페이지 범위 계산
                */
                $getPageRange = function ($visiblePages) use ($currentPage, $lastPage) {
                    $half = intdiv($visiblePages, 2);

                    $startPage = max(1, $currentPage - $half);
                    $endPage = min($lastPage, $startPage + $visiblePages - 1);
                    $startPage = max(1, $endPage - $visiblePages + 1);

                    return range($startPage, $endPage);
                };

                $desktopPages = $getPageRange(10);
                $mobilePages = $getPageRange(5);
            @endphp

            {{-- PC: 10개 --}}
            <ul class="pagination_nums pc-nums">
                @foreach ($desktopPages as $page)
                    <li
                        class="numbers {{ $page === $currentPage ? 'active' : '' }}"
                        @if ($page === $currentPage) aria-current="page" @endif
                    >
                        @if ($page === $currentPage)
                            <span>{{ $page }}</span>
                        @else
                            <a href="{{ $paginator->url($page) }}">{{ $page }}</a>
                        @endif
                    </li>
                @endforeach
            </ul>

            {{-- 모바일: 5개 --}}
            <ul class="pagination_nums mob-nums">
                @foreach ($mobilePages as $page)
                    <li
                        class="numbers {{ $page === $currentPage ? 'active' : '' }}"
                        @if ($page === $currentPage) aria-current="page" @endif
                    >
                        @if ($page === $currentPage)
                            <span>{{ $page }}</span>
                        @else
                            <a href="{{ $paginator->url($page) }}">{{ $page }}</a>
                        @endif
                    </li>
                @endforeach
            </ul>

            <div class="button_box">
                @if ($paginator->hasMorePages())
                    <div class="next">
                        <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('다음 페이지') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="7" height="12" viewBox="0 0 7 12" fill="none">
                            <path d="M0.118727 11.0115L4.85962 5.93344L-0.000112197 1.01753L1.02943 -7.21923e-06L6.8657 5.9045L1.17626 11.9991L0.118727 11.0115Z" fill="currentColor"/>
                            </svg>
                        </a>
                    </div>
                    <div class="next depth">
                        <a href="{{ $paginator->url($paginator->lastPage()) }}" aria-label="{{ __('마지막 페이지') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 14 14" fill="none">
                            <path d="M6.75 0.750001L0.749999 6.75L6.75 12.75" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M12.75 0.750001L6.75 6.75L12.75 12.75" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </a>
                    </div>
                @else
                    <div class="disabled next " aria-hidden="true">
                        <span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="7" height="12" viewBox="0 0 7 12" fill="none">
                            <path d="M0.118727 11.0115L4.85962 5.93344L-0.000112197 1.01753L1.02943 -7.21923e-06L6.8657 5.9045L1.17626 11.9991L0.118727 11.0115Z" fill="currentColor"/>
                            </svg>
                        </span>
                    </div>
                    <div class="disabled next depth" aria-hidden="true">
                        <span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 14 14" fill="none">
                            <path d="M6.75 0.750001L0.749999 6.75L6.75 12.75" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M12.75 0.750001L6.75 6.75L12.75 12.75" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                    </div>
                @endif
            </div>
        </div>
    </nav>
@endif
