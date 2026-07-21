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

        @if (auth()->check() || $board->allow_anonymous)
            <a href="{{ front_route('board.create', $board->slug) }}" class="btn btn-primary">{{ __('글쓰기') }}</a>
        @endif
    </div>

    @php
        // 전체공지(다른 게시판에도 노출되는 공지)는 갤러리 그리드가 아니라 표(리스트) 형태로 보여준다.
        // 이 게시판 자체의 공지(전체공지 아님)는 기존처럼 갤러리 그리드 안에 노출한다.
        $globalNoticesOnly = $notices->where('is_global_notice', true);
        $boardNoticesOnly = $notices->where('is_global_notice', false);
    @endphp

    @if ($globalNoticesOnly->isNotEmpty())
        <table class="board-list" style="margin-bottom: 20px;">
            <caption class="sr-only">{{ __('전체공지 목록') }}</caption>
            <tbody>
                @foreach ($globalNoticesOnly as $notice)
                    @php $noticeBoard = $notice->board; @endphp
                    <tr class="is-notice">
                        <td class="col-num"><span class="badge badge-notice">{{ __('전체공지') }}</span></td>
                        <td>
                            @if ($noticeBoard && $noticeBoard->id !== $board->id)
                                <span class="post-meta">[{{ $noticeBoard->name }}]</span>
                            @endif
                            <a href="{{ front_route('board.show', ['slug' => ($noticeBoard ?: $board)->slug, 'id' => $notice->id]) }}">
                                {{ $notice->title }}
                                @if ($notice->is_secret)<span class="badge badge-secret">{{ __('비밀글') }}</span>@endif
                            </a>
                        </td>
                        <td>{{ $notice->user?->nickname ?? $notice->user?->name ?? $notice->author_name ?? __('관리자') }}</td>
                        <td class="col-date">{{ local_datetime($notice->created_at)->format('Y-m-d') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($boardNoticesOnly->isNotEmpty())
        <div class="board-gallery-grid" style="margin-bottom: 20px;">
            @foreach ($boardNoticesOnly as $notice)
                @php $thumb = $notice->files->first(fn ($f) => str_starts_with((string) $f->mime_type, 'image/')); @endphp
                <a href="{{ front_route('board.show', ['slug' => $board->slug, 'id' => $notice->id]) }}" class="board-gallery-item">
                    @if ($thumb)
                        <img src="{{ url($thumb->file_path) }}" alt="">
                    @else
                        <div style="aspect-ratio:4/3; background: var(--color-bg-alt); display:flex; align-items:center; justify-content:center; color: var(--color-text-muted);">No Image</div>
                    @endif
                    <div class="board-gallery-body">
                        <span class="badge badge-notice">{{ __('공지') }}</span>
                        <p style="margin: 6px 0 0; font-weight: 600;">{{ $notice->title }}</p>
                        <p class="board-gallery-icons">
                            @if ($notice->files->isNotEmpty())<svg class="board-list-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-label="{{ __('첨부파일 있음') }}"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>@endif
                            @if ($board->allow_comment && $notice->comments_count)<span class="board-list-comment-count"><svg class="board-list-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>{{ $notice->comments_count }}</span>@endif
                        </p>
                        @include('partials.shared.recruitment-status', ['post' => $notice])
                    </div>
                </a>
            @endforeach
        </div>
    @endif

    <div class="board-gallery-grid">
        @forelse ($posts as $post)
            @php $thumb = $post->files->first(fn ($f) => str_starts_with((string) $f->mime_type, 'image/')); @endphp
            <a href="{{ front_route('board.show', ['slug' => $board->slug, 'id' => $post->id]) }}" class="board-gallery-item">
                @if ($thumb)
                    <img src="{{ url($thumb->file_path) }}" alt="">
                @else
                    <div style="aspect-ratio:4/3; background: var(--color-bg-alt); display:flex; align-items:center; justify-content:center; color: var(--color-text-muted);">No Image</div>
                @endif
                <div class="board-gallery-body">
                    <p style="margin: 0; font-weight: 600;">
                        {{ $post->title }}
                        @if ($post->is_secret)<span class="badge badge-secret">{{ __('비밀글') }}</span>@endif
                    </p>
                    <p class="post-meta" style="margin: 4px 0 0;">{{ $post->user?->nickname ?? $post->user?->name ?? $post->author_name ?? __('비회원') }} · {{ local_datetime($post->created_at)->format('Y-m-d') }}</p>
                    <p class="board-gallery-icons">
                        @if ($post->files->isNotEmpty())<svg class="board-list-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-label="{{ __('첨부파일 있음') }}"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>@endif
                        @if ($board->allow_comment && $post->comments_count)<span class="board-list-comment-count"><svg class="board-list-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>{{ $post->comments_count }}</span>@endif
                    </p>
                    @include('partials.shared.recruitment-status', ['post' => $post])
                </div>
            </a>
        @empty
            <p style="text-align:center; padding: 40px 0; color: var(--color-text-muted); grid-column: 1/-1;">{{ __('등록된 게시글이 없습니다.') }}</p>
        @endforelse
    </div>

    {{ $posts->links() }}
@endsection
