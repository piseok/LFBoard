@extends('layouts.subpage')

@section('subcontent')
    <article>
        <header class="post-header">
            <h1 class="post-title">
                @if ($post->is_global_notice)<span class="badge badge-notice">{{ __('전체공지') }}</span>@endif
                @if ($post->is_notice && ! $post->is_global_notice)<span class="badge badge-notice">{{ __('공지') }}</span>@endif
                @if ($post->is_secret)<span class="badge badge-secret">{{ __('비밀글') }}</span>@endif
                {{ $post->title }}
            </h1>
            <div class="post-meta">
                <span>{{ $post->user?->nickname ?? $post->user?->name ?? $post->author_name ?? __('비회원') }}</span>
                <time datetime="{{ $post->created_at->toIso8601String() }}">{{ local_datetime($post->created_at)->format('Y-m-d H:i') }}</time>
                <span>{{ __('조회') }} {{ $post->views }}</span>
                @if ($post->category)<span>{{ $post->category->name }}</span>@endif
            </div>
        </header>

        @if ($board->customFieldSchema())
            <dl class="board-custom-fields">
                @foreach ($board->customFieldSchema() as $field)
                    @php $value = $post->customFieldDisplay($field); @endphp
                    @if ($value !== null)
                        <div class="board-custom-field-row">
                            <dt>{{ $field['label'] }}</dt>
                            <dd>{{ $value }}</dd>
                        </div>
                    @endif
                @endforeach
            </dl>
        @endif

        <div class="post-content">{!! $content !!}</div>

        @if ($board->allow_file && $post->files->isNotEmpty())
            @php
                $imageFiles = $post->files->filter(fn ($f) => str_starts_with((string) $f->mime_type, 'image/'));
                $otherFiles = $post->files->reject(fn ($f) => str_starts_with((string) $f->mime_type, 'image/'));
            @endphp

            @if ($imageFiles->isNotEmpty())
                <div class="board-post-images">
                    @foreach ($imageFiles as $file)
                        <a href="{{ url($file->file_path) }}" target="_blank" rel="noopener">
                            <img src="{{ url($file->file_path) }}" alt="{{ $file->original_name }}">
                        </a>
                    @endforeach
                </div>
            @endif

            @if ($otherFiles->isNotEmpty())
                <div class="post-files">
                    <strong>{{ __('첨부파일') }}</strong>
                    <ul class="board-file-list">
                        @foreach ($otherFiles as $file)
                            <li>
                                <a href="{{ url($file->file_path) }}" download="{{ $file->original_name }}">{{ $file->original_name }}</a>
                                <span class="post-meta">({{ number_format($file->file_size / 1024, 1) }} KB)</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        @endif

        @if ($previousPost || $nextPost)
            <div class="board-post-nav">
                @if ($nextPost)
                    <a href="{{ front_route('board.show', ['slug' => $board->slug, 'id' => $nextPost->id]) }}" class="board-post-nav-item">
                        <span class="board-post-nav-label">{{ __('다음글') }}</span>
                        <span class="board-post-nav-title">{{ $nextPost->title }}</span>
                    </a>
                @endif
                @if ($previousPost)
                    <a href="{{ front_route('board.show', ['slug' => $board->slug, 'id' => $previousPost->id]) }}" class="board-post-nav-item">
                        <span class="board-post-nav-label">{{ __('이전글') }}</span>
                        <span class="board-post-nav-title">{{ $previousPost->title }}</span>
                    </a>
                @endif
            </div>
        @endif

        <div class="form-actions">
            <a href="{{ front_route('board.index', $board->slug) }}" class="btn">{{ __('목록') }}</a>
            @if ($canModify)
                <a href="{{ front_route('board.edit', ['slug' => $board->slug, 'id' => $post->id]) }}" class="btn">{{ __('수정') }}</a>
                <form method="POST" action="{{ front_route('board.destroy', ['slug' => $board->slug, 'id' => $post->id]) }}" data-confirm="{{ __('삭제하시겠습니까?') }}" style="display:inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">{{ __('삭제') }}</button>
                </form>
            @elseif (is_null($post->user_id))
                <a href="{{ front_route('board.edit', ['slug' => $board->slug, 'id' => $post->id]) }}" class="btn">{{ __('비밀번호 확인 후 수정/삭제') }}</a>
            @endif
        </div>
    </article>

    @include('board._comments', ['board' => $board, 'post' => $post, 'comments' => $comments])
@endsection
