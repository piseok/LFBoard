@extends('layouts.subpage')

@section('subcontent')
    <h1 class="page-title">{{ $board->name }}</h1>

    @if ($board->description)
        <p class="post-meta">{{ $board->description }}</p>
    @endif

    @if ($categories->isNotEmpty())
        <nav class="board-category-filter" aria-label="{{ __('카테고리 필터') }}">
            <a href="{{ front_route('board.index', $board->slug) }}" class="{{ request()->filled('category') ? '' : 'is-active' }}">{{ __('전체') }}</a>
            @foreach ($categories as $category)
                <a href="{{ front_route('board.index', ['slug' => $board->slug, 'category' => $category->id]) }}"
                   class="{{ (int) request('category') === $category->id ? 'is-active' : '' }}">{{ $category->name }}</a>
            @endforeach
        </nav>
    @endif

    <div class="board-toolbar">
      <div class="board-toolbar-left">
        <p class="board-count-info">{{ __('총 :total건 (:current/:last 페이지)', ['total' => $posts->total(), 'current' => $posts->currentPage(), 'last' => max($posts->lastPage(), 1)]) }}</p>
        <form method="GET" action="{{ front_route('board.index', $board->slug) }}" class="board-search-form">
            @if (request()->filled('category'))
                <input type="hidden" name="category" value="{{ request('category') }}">
            @endif
            @if ($hasRecruitmentPosts)
                <label for="recruitment_status" class="sr-only">{{ __('모집 상태') }}</label>
                <select id="recruitment_status" name="recruitment_status">
                    <option value="" @selected(request('recruitment_status', '') === '')>{{ __('모집 상태 전체') }}</option>
                    <option value="예정" @selected(request('recruitment_status') === '예정')>{{ __('접수예정') }}</option>
                    <option value="기간중" @selected(request('recruitment_status') === '기간중')>{{ __('접수중') }}</option>
                    <option value="마감" @selected(request('recruitment_status') === '마감')>{{ __('접수마감') }}</option>
                </select>
            @endif
            <label for="search_type" class="sr-only">{{ __('검색 조건') }}</label>
            <select id="search_type" name="search_type">
                <option value="title" @selected(request('search_type', 'title') === 'title')>{{ __('제목') }}</option>
                <option value="content" @selected(request('search_type') === 'content')>{{ __('내용') }}</option>
                <option value="author" @selected(request('search_type') === 'author')>{{ __('작성자') }}</option>
            </select>
            <label for="q" class="sr-only">{{ __('검색어') }}</label>
            <input type="text" id="q" name="q" value="{{ request('q') }}" placeholder="{{ __('검색어 입력') }}">
            <button type="submit" class="btn">{{ __('검색') }}</button>
        </form>
      </div>

        @auth
            <a href="{{ front_route('board.create', $board->slug) }}" class="btn btn-primary">{{ __('글쓰기') }}</a>
        @else
            @if ($board->allow_anonymous)
                <a href="{{ front_route('board.create', $board->slug) }}" class="btn btn-primary">{{ __('글쓰기') }}</a>
            @endif
        @endauth
    </div>

    @php $colCount = 5 + ($categories->isNotEmpty() ? 1 : 0) + ($hasRecruitmentPosts ? 1 : 0) + ($board->allow_comment ? 1 : 0); @endphp
    <table class="board-list">
        <caption class="sr-only">{{ __(':name 게시글 목록', ['name' => $board->name]) }}</caption>
        <thead>
            <tr>
                <th scope="col" class="col-num">{{ __('번호') }}</th>
                @if ($categories->isNotEmpty())
                    <th scope="col">{{ __('카테고리') }}</th>
                @endif
                <th scope="col">{{ __('제목') }}</th>
                @if ($hasRecruitmentPosts)
                    <th scope="col" class="col-recruitment">{{ __('모집기간') }}</th>
                @endif
                <th scope="col">{{ __('작성자') }}</th>
                <th scope="col" class="col-date">{{ __('작성일') }}</th>
                <th scope="col" class="col-views">{{ __('조회') }}</th>
                @if ($board->allow_comment)
                    <th scope="col" class="col-views">{{ __('댓글') }}</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach ($notices as $notice)
                @php $noticeBoard = $notice->is_global_notice ? $notice->board : $board; @endphp
                <tr class="is-notice">
                    <td class="col-num"><span class="badge badge-notice">{{ $notice->is_global_notice ? __('전체공지') : __('공지') }}</span></td>
                    @if ($categories->isNotEmpty())
                        <td>{{ $notice->category?->name ?? '-' }}</td>
                    @endif
                    <td>
                        @if ($notice->is_global_notice && $noticeBoard && $noticeBoard->id !== $board->id)
                            <span class="post-meta">[{{ $noticeBoard->name }}]</span>
                        @endif
                        <a href="{{ front_route('board.show', ['slug' => $noticeBoard->slug, 'id' => $notice->id]) }}">
                            {{ $notice->title }}
                            @if ($notice->is_secret)<span class="badge badge-secret">{{ __('비밀글') }}</span>@endif
                            @if ($notice->files->isNotEmpty())<svg class="board-list-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-label="{{ __('첨부파일 있음') }}"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>@endif
                        </a>
                    </td>
                    @if ($hasRecruitmentPosts)
                        <td class="col-recruitment">@include('partials.shared.recruitment-period-cell', ['post' => $notice])</td>
                    @endif
                    <td>{{ $notice->user?->nickname ?? $notice->user?->name ?? $notice->author_name ?? __('관리자') }}</td>
                    <td class="col-date">{{ local_datetime($notice->created_at)->format('Y-m-d') }}</td>
                    <td class="col-views">{{ $notice->views }}</td>
                    @if ($board->allow_comment)
                        <td class="col-views">{{ $notice->comments_count ?? 0 }}</td>
                    @endif
                </tr>
            @endforeach

            @forelse ($posts as $post)
                <tr>
                    <td class="col-num">{{ $posts->total() - (($posts->currentPage() - 1) * $posts->perPage()) - $loop->index }}</td>
                    @if ($categories->isNotEmpty())
                        <td>{{ $post->category?->name ?? '-' }}</td>
                    @endif
                    <td>
                        <a href="{{ front_route('board.show', ['slug' => $board->slug, 'id' => $post->id]) }}">
                            {{ $post->title }}
                            @if ($post->is_secret)<span class="badge badge-secret">{{ __('비밀글') }}</span>@endif
                            @if ($post->files->isNotEmpty())<svg class="board-list-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-label="{{ __('첨부파일 있음') }}"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>@endif
                        </a>
                    </td>
                    @if ($hasRecruitmentPosts)
                        <td class="col-recruitment">@include('partials.shared.recruitment-period-cell', ['post' => $post])</td>
                    @endif
                    <td>{{ $post->user?->nickname ?? $post->user?->name ?? $post->author_name ?? __('비회원') }}</td>
                    <td class="col-date">{{ local_datetime($post->created_at)->format('Y-m-d') }}</td>
                    <td class="col-views">{{ $post->views }}</td>
                    @if ($board->allow_comment)
                        <td class="col-views">{{ $post->comments_count }}</td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $colCount }}" style="text-align:center; padding: 40px 0; color: var(--color-text-muted);">{{ __('등록된 게시글이 없습니다.') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{ $posts->links() }}
@endsection
