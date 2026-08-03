@extends('layouts.app')

@section('content')
    <x-sub-header :title="__('통합검색')" />

    <div class="board-toolbar">
        <form method="GET" action="{{ front_route('search.index') }}" class="board-search-form">
            <label for="q" class="sr-only">{{ __('검색어') }}</label>
            <input type="text" id="q" name="q" value="{{ $keyword }}" placeholder="{{ __('검색어 입력') }}" autofocus>
            <button type="submit" class="btn">{{ __('검색') }}</button>
        </form>
    </div>

    @if ($keyword === '')
        <p class="post-meta">{{ __('검색어를 입력해 주세요.') }}</p>
    @elseif ($resultsByBoard->isEmpty())
        <p class="post-meta">{{ __(':keyword 검색 결과가 없습니다.', ['keyword' => $keyword]) }}</p>
    @else
        <p class="post-meta">{{ __(':keyword 검색 결과 :count건', ['keyword' => $keyword, 'count' => $totalCount]) }}</p>

        <nav class="board-category-filter" aria-label="{{ __('게시판별 결과') }}">
            @foreach ($resultsByBoard as $slug => $result)
                <a href="{{ front_route('search.index', ['q' => $keyword, 'board' => $slug]) }}"
                   class="{{ $activeBoardSlug === $slug ? 'is-active' : '' }}">{{ $result['board']->name }} ({{ $result['count'] }})</a>
            @endforeach
        </nav>

        @if ($activeResult = $resultsByBoard->get($activeBoardSlug))
            <table class="board-list">
                <caption class="sr-only">{{ __(':name 검색 결과', ['name' => $activeResult['board']->name]) }}</caption>
                <thead>
                    <tr>
                        <th scope="col">{{ __('제목') }}</th>
                        <th scope="col">{{ __('작성자') }}</th>
                        <th scope="col" class="col-date">{{ __('작성일') }}</th>
                        <th scope="col" class="col-views">{{ __('조회') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($activeResult['posts'] as $post)
                        <tr>
                            <td>
                                <a href="{{ front_route('board.show', ['slug' => $activeResult['board']->slug, 'id' => $post->id]) }}">
                                    {{ $post->title }}
                                    @if ($post->is_secret)<span class="badge badge-secret">{{ __('비밀글') }}</span>@endif
                                </a>
                            </td>
                            <td>{{ $post->user?->nickname ?? $post->user?->name ?? $post->author_name ?? __('비회원') }}</td>
                            <td class="col-date">{{ local_datetime($post->created_at)->format('Y-m-d') }}</td>
                            <td class="col-views">{{ $post->views }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endif
@endsection
