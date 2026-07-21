@extends('layouts.subpage')

@php
    // 검색조건 select에서 커스텀필드를 선택했을 때, select/radio/checkbox 타입은 자유 텍스트가 아니라
    // 그 필드의 선택지와 정확히 일치하는 값으로 검색해야 하므로 별도의 <select name="q">를 미리
    // 렌더링해두고(board-search.js가 토글) 어떤 필드가 지금 선택된 상태인지 계산해둔다.
    $currentSearchType = request('search_type', 'title');
    $activeCustomChoiceField = collect($board->customFieldSchema())
        ->first(fn ($f) => $currentSearchType === "custom:{$f['key']}" && in_array($f['type'], ['select', 'radio', 'checkbox'], true));
@endphp

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
        <form method="GET" action="{{ front_route('board.index', $board->slug) }}" class="board-search-form" data-custom-search>
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
            <select id="search_type" name="search_type" class="board-search-type">
                <option value="title" @selected($currentSearchType === 'title')>{{ __('제목') }}</option>
                <option value="content" @selected($currentSearchType === 'content')>{{ __('내용') }}</option>
                <option value="author" @selected($currentSearchType === 'author')>{{ __('작성자') }}</option>
                @foreach ($board->customFieldSchema() as $field)
                    <option value="custom:{{ $field['key'] }}" data-field-type="{{ $field['type'] }}" @selected($currentSearchType === "custom:{$field['key']}")>{{ $field['label'] }}</option>
                @endforeach
            </select>
            <label for="q" class="sr-only">{{ __('검색어') }}</label>
            <input type="text" id="q" name="q" class="board-search-value board-search-value-default"
                   value="{{ $activeCustomChoiceField ? '' : request('q') }}"
                   placeholder="{{ __('검색어 입력') }}"
                   @if ($activeCustomChoiceField) disabled hidden @endif>
            @foreach ($board->customFieldSchema() as $field)
                @continue(! in_array($field['type'], ['select', 'radio', 'checkbox'], true))
                @php $isActiveField = $activeCustomChoiceField && $activeCustomChoiceField['key'] === $field['key']; @endphp
                <select name="q" class="board-search-value board-search-value-option" data-search-for="custom:{{ $field['key'] }}" @unless ($isActiveField) disabled hidden @endunless>
                    <option value="">{{ __('선택') }}</option>
                    @foreach ($field['options'] ?? [] as $option)
                        <option value="{{ $option }}" @selected($isActiveField && request('q') === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            @endforeach
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

    @php $colCount = 5 + ($categories->isNotEmpty() ? 1 : 0) + ($hasRecruitmentPosts ? 1 : 0) + ($board->allow_comment ? 1 : 0) + count($board->customFieldSchema()); @endphp
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
                @foreach ($board->customFieldSchema() as $field)
                    <th scope="col">{{ $field['label'] }}</th>
                @endforeach
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
                    @foreach ($board->customFieldSchema() as $field)
                        <td>{{ $notice->customFieldDisplay($field) ?? '-' }}</td>
                    @endforeach
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
                    @foreach ($board->customFieldSchema() as $field)
                        <td>{{ $post->customFieldDisplay($field) ?? '-' }}</td>
                    @endforeach
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

    @push('scripts')
        <script src="{{ asset('js/board-search.js') }}" defer></script>
    @endpush
@endsection
