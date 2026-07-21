@php
    $authorLabel = $comment->user?->nickname ?? $comment->user?->name ?? $comment->author_name ?? __('비회원');
    $canDeleteComment = auth()->check()
        ? ($comment->user_id === auth()->id() || auth()->user()->level === \App\Models\User::LEVEL_ADMIN)
        : is_null($comment->user_id);
@endphp

<div class="comment-body">
    <div class="board-comment-meta">
        <strong class="comment-author">{{ $authorLabel }}</strong>
        <time class="comment-date" datetime="{{ $comment->created_at->toIso8601String() }}">{{ local_datetime($comment->created_at)->format('Y-m-d H:i') }}</time>
    </div>
    <p class="board-comment-content">{{ $comment->content }}</p>

    <div class="board-comment-actions">
        @if ($board->allow_reply && is_null($comment->parent_id))
            <button type="button" class="btn-link reply-toggle-btn" data-target="reply-form-{{ $comment->id }}">{{ __('답글') }}</button>
        @endif

        @if ($canDeleteComment)
            <form method="POST" action="{{ front_route('comment.destroy', $comment->id) }}" class="board-comment-delete-form" data-confirm="{{ __('댓글을 삭제하시겠습니까?') }}">
                @csrf
                @method('DELETE')
                @guest
                    <label for="comment-delete-password-{{ $comment->id }}" class="sr-only">{{ __('삭제 비밀번호') }}</label>
                    <input type="password" id="comment-delete-password-{{ $comment->id }}" name="author_password" placeholder="{{ __('비밀번호') }}" maxlength="20" required class="board-comment-delete-password">
                @endguest
                <button type="submit" class="btn-link btn-link-danger">{{ __('삭제') }}</button>
            </form>
        @endif
    </div>

    @if ($board->allow_reply && is_null($comment->parent_id))
        <form id="reply-form-{{ $comment->id }}" method="POST" action="{{ front_route('comment.store', ['slug' => $board->slug, 'id' => $post->id]) }}" class="board-comment-form is-hidden board-reply-form">
            @csrf
            <input type="hidden" name="parent_id" value="{{ $comment->id }}">
            <div class="form-group">
                <label for="reply-content-{{ $comment->id }}" class="sr-only">{{ __('답글 내용') }}</label>
                <textarea id="reply-content-{{ $comment->id }}" name="content" rows="2" required maxlength="2000" placeholder="{{ __('답글을 입력하세요') }}"></textarea>
            </div>
            @guest
                <div class="form-row">
                    <input type="text" name="author_name" placeholder="{{ __('이름') }}" maxlength="50" required>
                    <input type="password" name="author_password" placeholder="{{ __('비밀번호') }}" maxlength="20" required>
                </div>
            @endguest
            <button type="submit" class="btn btn-primary btn-sm">{{ __('답글 등록') }}</button>
        </form>
    @endif
</div>
