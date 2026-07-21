@if ($board->allow_comment)
    <section class="board-comments" aria-label="{{ __('댓글') }}">
        <h2>{{ __('댓글') }} ({{ $comments->count() + $comments->sum(fn ($c) => $c->replies->count()) }})</h2>

        <ul class="board-comment-list">
            @forelse ($comments as $comment)
                <li id="comment-{{ $comment->id }}" class="board-comment">
                    @include('board._comment-item', ['comment' => $comment])
                </li>

                @foreach ($comment->replies as $reply)
                    <li id="comment-{{ $reply->id }}" class="board-comment is-reply">
                        @include('board._comment-item', ['comment' => $reply])
                    </li>
                @endforeach
            @empty
                <li class="board-comment-empty">{{ __('등록된 댓글이 없습니다.') }}</li>
            @endforelse
        </ul>

        <form method="POST" action="{{ front_route('comment.store', ['slug' => $board->slug, 'id' => $post->id]) }}" class="board-comment-form">
            @csrf
            <div class="form-group">
                <label for="comment-content" class="sr-only">{{ __('댓글 내용') }}</label>
                <textarea id="comment-content" name="content" rows="3" required maxlength="2000" placeholder="{{ __('댓글을 입력하세요') }}">{{ old('content') }}</textarea>
            </div>

            @auth
            @else
                <div class="form-row">
                    <div class="form-group">
                        <label for="comment-author-name" class="sr-only">{{ __('이름') }}</label>
                        <input type="text" id="comment-author-name" name="author_name" placeholder="{{ __('이름') }}" maxlength="50" required>
                    </div>
                    <div class="form-group">
                        <label for="comment-author-password" class="sr-only">{{ __('비밀번호') }}</label>
                        <input type="password" id="comment-author-password" name="author_password" placeholder="{{ __('비밀번호(수정/삭제 시 필요)') }}" maxlength="20" required>
                    </div>
                </div>
            @endauth

            @error('content')
                <p class="field-error">{{ $message }}</p>
            @enderror

            <button type="submit" class="btn btn-primary btn-sm">{{ __('댓글 등록') }}</button>
        </form>
    </section>
@endif
