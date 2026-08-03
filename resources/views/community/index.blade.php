@extends('layouts.app')

@section('content')
    <x-sub-header :title="$pageTitle">
        <x-slot:nav>
            <nav class="board-category-filter" aria-label="{{ __('게시판별 보기') }}">
                <a href="{{ front_route('community.index') }}" class="{{ $activeBoardSlug === '' ? 'is-active' : '' }}">{{ __('전체') }}</a>
                @foreach ($boards as $board)
                    <a href="{{ front_route('community.index', ['board' => $board->slug]) }}" class="{{ $activeBoardSlug === $board->slug ? 'is-active' : '' }}">{{ $board->name }}</a>
                @endforeach
            </nav>
        </x-slot:nav>
    </x-sub-header>

    <div class="board-toolbar">
        <form method="GET" action="{{ front_route('community.index') }}" class="board-search-form">
            @if ($activeBoardSlug !== '')
                <input type="hidden" name="board" value="{{ $activeBoardSlug }}">
            @endif
            <label for="search_type" class="sr-only">{{ __('검색 조건') }}</label>
            <select id="search_type" name="search_type">
                <option value="title" @selected(request('search_type', 'title') === 'title')>{{ __('제목') }}</option>
                <option value="content" @selected(request('search_type') === 'content')>{{ __('내용') }}</option>
                <option value="author" @selected(request('search_type') === 'author')>{{ __('작성자') }}</option>
            </select>
            <label for="q" class="sr-only">{{ __('검색어') }}</label>
            <input type="text" id="q" name="q" value="{{ $keyword }}" placeholder="{{ __('검색어 입력') }}">
            <button type="submit" class="btn">{{ __('검색') }}</button>
        </form>
    </div>

    @if ($keyword !== '')
        <p class="post-meta">{{ __(':keyword 검색 결과 :count건', ['keyword' => $keyword, 'count' => $posts->total()]) }}</p>
    @endif

    @if ($posts->isEmpty())
        <p class="post-meta">{{ __('등록된 게시글이 없습니다.') }}</p>
    @else
        <table class="board-list">
            <caption class="sr-only">{{ $pageTitle }}</caption>
            <thead>
                <tr>
                    <th scope="col">{{ __('게시판') }}</th>
                    <th scope="col">{{ __('제목') }}</th>
                    <th scope="col">{{ __('작성자') }}</th>
                    <th scope="col" class="col-date">{{ __('작성일') }}</th>
                    <th scope="col" class="col-views">{{ __('조회') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($posts as $post)
                    <tr>
                        <td>{{ $post->board->name }}</td>
                        <td>
                            <a href="{{ front_route('board.show', ['slug' => $post->board->slug, 'id' => $post->id]) }}">
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

        {{ $posts->links() }}
    @endif
@endsection
