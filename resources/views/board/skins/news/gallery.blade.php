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
    <x-sub-header>
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
        <div class="board-news-list gallery">
            @forelse ($posts as $post)
                <article class="news-list-item">
                    <figure class="thumb">
                        @php
                            $thumbSrc = null;
                            $thumbAlt = $post->title;
                            $postContent = (string) $post->content;

                            if ($postContent !== '') {
                                $document = new \DOMDocument();
                                $previousErrors = libxml_use_internal_errors(true);
                                try {
                                    $document->loadHTML('<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>' . $postContent . '</body></html>');
                                    foreach ($document->getElementsByTagName('img') as $image) {
                                        $src = trim($image->getAttribute('src'));
                                        if ($src !== '') {
                                            $thumbSrc = $src;
                                            $thumbAlt = $image->getAttribute('alt') ?: $post->title;
                                            break;
                                        }
                                    }
                                } finally {
                                    libxml_clear_errors();
                                    libxml_use_internal_errors($previousErrors);
                                }
                            }

                            if ($thumbSrc === null) {
                                $thumb = $post->files->first(fn ($file) => str_starts_with((string) $file->mime_type, 'image/'));
                                if ($thumb) {
                                    $thumbSrc = url($thumb->file_path);
                                    $thumbAlt = $thumb->original_name;
                                }
                            }
                        @endphp
                        @if ($thumbSrc)
                            <img src="{{ $thumbSrc }}" alt="{{ $thumbAlt }}" draggable="false">
                        @else
                            <x-no-img />
                        @endif
                    </figure>
                    

                    <div class="content">
                        <div class="title-box">
                            <a class="title ellipsis" href="{{ front_route('board.show', ['slug' => $board->slug, 'id' => $post->id]) }}">{{ $post->title }}</a>
                        </div>
                        <div class="post-meta">
                            <span class="name">작성자: {{ $post->user?->nickname ?? $post->user?->name ?? $post->author_name ?? __('비회원') }}</span>
                            <span class="date">작성일: {{ local_datetime($post->created_at)->format('Y-m-d') }}</span>
                            <span class="views">조회: {{ $post->views }}</span>
                        </div>

                        <p class="cont-text ellipsis">
                            {{ strip_tags((string) $post->content) }}
                        </p>
                    </div>
                </article>
            @empty
                <x-no-post />
            @endforelse
        </div>

    {{ $posts->links() }}

    @push('scripts')
        <script src="{{ asset('js/board-search.js') }}" defer></script>

        <!-- 직원소개 게시판은 직원들 이미지를 올리므로 도용을 막기 위해 이미지 드래그와 우클릭을 막아놓는다. (개발자모드로는 뚫리지만 일반 사용자들은 개발자모드 존재도 모르기에 이 부분까지는 허용.) -->
        <script nonce="{{ $cspNonce }}">
            document.querySelectorAll('.board-pop-list .thumb').forEach(function (thumb) {
                ['contextmenu', 'dragstart'].forEach(function (eventName) {
                    thumb.addEventListener(eventName, function (event) {
                        event.preventDefault();
                    });
                });
            });
        </script>
    @endpush
@endsection
