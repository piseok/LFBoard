@extends('layouts.subpage')

@section('subcontent')
    <x-sub-header :title="$board->name" :description="$board->description">
        @if ($categories->isNotEmpty())
            <x-slot:nav>
                <nav class="board-category-filter" aria-label="{{ __('카테고리 필터') }}">
                    <a href="{{ front_route('board.index', $board->slug) }}" class="{{ request()->filled('category') ? '' : 'is-active' }}">{{ __('전체') }}</a>
                    @foreach ($categories as $category)
                        <a href="{{ front_route('board.index', ['slug' => $board->slug, 'category' => $category->id]) }}"
                           class="{{ (int) request('category') === $category->id ? 'is-active' : '' }}">{{ $category->name }}</a>
                    @endforeach
                </nav>
            </x-slot:nav>
        @endif
    </x-sub-header>

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

    {{--
        FAQ는 목록 페이지에서 바로 질문을 펼쳐 답을 보여주는 아코디언 형태가 자연스러워서,
        default 스킨처럼 상세페이지로 이동시키지 않고 <details>/<summary>로 인라인 처리한다
        (별도 JS 없이 네이티브 접근성 지원). 답변 내용은 board.show()의 새니타이즈 로직과 동일하게
        처리해야 하므로 여기서도 같은 규칙(에디터=HTML sanitize, 일반=이스케이프+줄바꿈)을 따른다.
    --}}
    @php $sanitizer = app(\App\Services\HtmlSanitizerService::class); @endphp

    <div class="board-faq-list">
        @foreach ($notices as $notice)
            @php
                $noticeBoard = $notice->is_global_notice ? $notice->board : $board;
                $answerHtml = $board->use_editor
                    ? $sanitizer->clean((string) $notice->content)
                    : nl2br(e((string) $notice->content));
            @endphp
            <div class="board-faq-item" data-toggle="hidden">
                <div class="board-faq-question">
                    <span class="board-faq-q-badge">Q</span>
                    <p>
                        <span class="badge badge-notice">{{ $notice->is_global_notice ? __('전체공지') : __('공지') }}</span>
                        @if ($notice->is_global_notice && $noticeBoard && $noticeBoard->id !== $board->id)
                            <span class="post-meta">[{{ $noticeBoard->name }}]</span>
                        @endif
                        {{ $notice->title }}
                        @if ($notice->is_secret)<span class="badge badge-secret">{{ __('비밀글') }}</span>@endif
                        @if ($notice->files->isNotEmpty())<svg class="board-list-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-label="{{ __('첨부파일 있음') }}"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>@endif
                    </p>
                    <button type="button" class="faq-toggle"></button>
                </div>

                <div class="board-faq-answer">
                    <div class="board-faq-answer-inner">
                        <span class="board-faq-a-badge">A</span>
                        <div class="faq-content">{!! $answerHtml !!}</div>
                    </div>
                </div>
            </div>
        @endforeach

        @forelse ($posts as $post)
            @php
                $answerHtml = $board->use_editor
                    ? $sanitizer->clean((string) $post->content)
                    : nl2br(e((string) $post->content));
            @endphp
            <div class="board-faq-item" data-toggle="hidden">
                <div class="board-faq-question">
                    <div class="faq-header">
                        <span class="board-faq-q-badge">Q</span>
                        <p>
                            {{ $post->title }}
                            @if ($post->is_secret)<span class="badge badge-secret">{{ __('비밀글') }}</span>@endif
                            @if ($post->files->isNotEmpty())<svg class="board-list-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-label="{{ __('첨부파일 있음') }}"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>@endif
                        </p>
                    </div>
                    <button type="button" class="faq-toggle">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="7" viewBox="0 0 12 7" fill="none">
                        <path d="M0.987342 0.118727L6.06539 4.85962L10.9813 -0.000113641L11.9988 1.02943L6.09433 6.8657L-0.000270235 1.17626L0.987342 0.118727Z" fill="#69707D"/>
                        </svg>
                    </button>
                </div>
                <div class="board-faq-answer">
                    <div class="board-faq-answer-inner">
                        <div class="answer-header">
                            <span class="board-faq-a-badge">A</span>

                            @if ($board->allow_comment || ($board->allow_file && $post->files->isNotEmpty()))
                                <a href="{{ front_route('board.show', ['slug' => $board->slug, 'id' => $post->id]) }}" class="board-faq-detail-link">{{ __('상세보기 (댓글/첨부파일)') }} &rarr;</a>
                            @endif
                        </div>
                        <div class="faq-content">{!! $answerHtml !!}</div>
                    </div>
                </div>
            </div>
        @empty
            <p style="text-align:center; padding: 40px 0; color: var(--color-text-muted);">{{ __('등록된 게시글이 없습니다.') }}</p>
        @endforelse
    </div>

    {{ $posts->links() }}
@endsection
