@extends('layouts.app')

@section('content')
    {{--
        2026-10-12: 기존의 게시판 하나씩 조회하는 테이블을 전체/게시판별 결과 목록으로 변경.
        검색어와 총 건수를 상단에 표시하고, 결과마다 경로·제목·본문 요약·작성 정보를 배치한다.
        검색 조건과 조회 권한은 SearchController에서 전달하는 기존 결과를 그대로 사용한다.
    --}}
    @php
        // 2026-10-12: board 파라미터가 없으면 첫 게시판 대신 전체 결과를 표시한다.
        // 게시판 탭을 선택하면 기존 board 파라미터로 해당 게시판의 결과만 표시한다.
        $showAllResults = ! request()->filled('board');
        $visibleResults = $showAllResults ? $resultsByBoard : $resultsByBoard->only($activeBoardSlug);

        // 2026-10-12: 제목과 요약의 검색어를 강조한다. 원문을 분리한 뒤 이스케이프하여
        // 검색어에 HTML이나 정규식 특수문자가 포함되어도 안전하게 문자로 표시한다.
        $highlightKeyword = static function (string $text) use ($keyword): string {
            if ($keyword === '') {
                return e($text);
            }

            $parts = preg_split('/('.preg_quote($keyword, '/').')/iu', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
            if ($parts === false) {
                return e($text);
            }

            $html = '';
            foreach ($parts as $index => $part) {
                $html .= $index % 2 === 1
                    ? '<mark class="search-highlight">'.e($part).'</mark>'
                    : e($part);
            }

            return $html;
        };
    @endphp

    <div class="search-page">
        {{-- 2026-10-12: 기존 통합검색 제목과 툴바를 중앙 정렬된 검색 요약 및 아이콘 검색창으로 변경. --}}
        <section class="search-hero" aria-labelledby="search-summary">
            <div class="container search-hero-inner">
                <h1 id="search-summary" class="search-summary">
                    @if ($keyword === '')
                        {{ __('통합검색') }}
                    @else
                        {!! __('검색어 :keyword에 대해 총 :count건을 찾았습니다.', [
                            'keyword' => '<span class="search-summary-keyword">'.e($keyword).'</span>',
                            'count' => '<span class="search-summary-count">'.number_format($totalCount).'</span>',
                        ]) !!}
                    @endif
                </h1>

                <form method="GET" action="{{ front_route('search.index') }}" class="search-form" role="search">
                    <label for="q" class="sr-only">{{ __('검색어') }}</label>
                    <input type="text" id="q" name="q" value="{{ $keyword }}" placeholder="{{ __('검색어 입력') }}" autofocus>
                    <button type="submit" class="search-submit" aria-label="{{ __('검색') }}">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false">
                            <circle cx="10.5" cy="10.5" r="7.5" />
                            <path d="m16 16 5 5" />
                        </svg>
                    </button>
                </form>
            </div>
        </section>

        <div class="container search-results">
            @if ($keyword === '')
                <p class="search-empty">{{ __('검색어를 입력해 주세요.') }}</p>
            @elseif ($resultsByBoard->isEmpty())
                <p class="search-empty">{{ __(':keyword 검색 결과가 없습니다.', ['keyword' => $keyword]) }}</p>
            @else
                {{-- 2026-10-12: 게시판 탭 앞에 전체 탭을 추가하고, 각 탭에 실제 검색 건수를 표시한다. --}}
                <nav class="search-tabs" aria-label="{{ __('게시판별 결과') }}">
                    <a href="{{ front_route('search.index', ['q' => $keyword]) }}"
                       class="{{ $showAllResults ? 'is-active' : '' }}" @if ($showAllResults) aria-current="page" @endif>
                        {{ __('전체') }} ({{ number_format($totalCount) }})
                    </a>
                    @foreach ($resultsByBoard as $slug => $result)
                        <a href="{{ front_route('search.index', ['q' => $keyword, 'board' => $slug]) }}"
                           class="{{ ! $showAllResults && $activeBoardSlug === $slug ? 'is-active' : '' }}"
                           @if (! $showAllResults && $activeBoardSlug === $slug) aria-current="page" @endif>
                            {{ $result['board']->name }} ({{ number_format($result['count']) }})
                        </a>
                    @endforeach
                </nav>

                {{--
                    2026-10-12: 테이블 행 대신 게시판별 제목과 결과 목록을 표시한다.
                    더보기는 검색어와 제목 검색 조건을 유지한 채 해당 게시판 목록으로 이동한다.
                    검색 결과가 없는 게시판은 기존 컨트롤러의 결과에 포함되지 않아 표시하지 않는다.
                --}}
                @forelse ($visibleResults as $slug => $result)
                    <section class="search-board-section" aria-labelledby="search-board-{{ $loop->index }}">
                        <header class="search-board-header">
                            <h2 id="search-board-{{ $loop->index }}" class="search-board-title">
                                {{ $result['board']->name }} <span>({{ number_format($result['count']) }})</span>
                            </h2>
                            <a href="{{ front_route('board.index', ['slug' => $slug, 'q' => $keyword, 'search_type' => 'title']) }}"
                               class="search-board-more" aria-label="{{ __(':name 검색 결과 더보기', ['name' => $result['board']->name]) }}">
                                {{ __('더보기') }} <span class="search-more-icon" aria-hidden="true"></span>
                            </a>
                        </header>

                        <div class="search-result-list">
                            @foreach ($result['posts'] as $post)
                                @php
                                    // 2026-10-12: 기존 제목 중심 목록에 본문 요약을 추가한다.
                                    // HTML 태그를 제거하고 공백을 정리한 뒤 최대 180자로 줄여 표시한다.
                                    $excerpt = html_entity_decode(strip_tags((string) $post->content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                                    $excerpt = \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/u', ' ', $excerpt) ?? ''), 180);
                                @endphp
                                <article class="search-result-item">
                                    <p class="search-result-path">
                                        <a href="{{ front_route('home') }}">{{ __('홈') }}</a>
                                        <span aria-hidden="true">&gt;</span>
                                        <a href="{{ front_route('board.index', $slug) }}">{{ $result['board']->name }}</a>
                                    </p>
                                    <h3 class="search-result-title">
                                        <a href="{{ front_route('board.show', ['slug' => $slug, 'id' => $post->id]) }}">
                                            {!! $highlightKeyword((string) $post->title) !!}
                                            @if ($post->is_secret)<span class="badge badge-secret">{{ __('비밀글') }}</span>@endif
                                        </a>
                                    </h3>
                                    @if ($excerpt !== '')
                                        <p class="search-result-excerpt">{!! $highlightKeyword($excerpt) !!}</p>
                                    @endif
                                    {{-- 2026-10-12: 기존 작성자·작성일·조회수 컬럼을 결과 하단의 작성 정보로 배치. --}}
                                    <div class="search-result-meta">
                                        <span>{{ $post->user?->nickname ?? $post->user?->name ?? $post->author_name ?? __('비회원') }}</span>
                                        <time datetime="{{ $post->created_at->toIso8601String() }}">{{ local_datetime($post->created_at)->format('Y-m-d') }}</time>
                                        <span>{{ __('조회') }} {{ number_format($post->views) }}</span>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @empty
                    <p class="search-empty">{{ __('검색 결과가 없습니다.') }}</p>
                @endforelse
            @endif
        </div>
    </div>
@endsection
