{{-- 카드형 스킨 — 제목 + 내용 미리보기 + 작성일을 그리드 카드로 보여준다. --}}
<div class="latest-posts latest-posts-card">
    <div class="latest-posts-header">
        <h2 class="latest-posts-title">{{ $board->name }}</h2>
        <a href="{{ front_route('board.index', $board->slug) }}" class="latest-posts-more">{{ __('더보기') }}</a>
    </div>

    @if ($posts->isEmpty())
        <p class="latest-posts-empty">{{ __('등록된 게시글이 없습니다.') }}</p>
    @else
        <div class="latest-posts-card-grid">
            @foreach ($posts as $post)
                <a href="{{ front_route('board.show', ['slug' => $board->slug, 'id' => $post->id]) }}" class="latest-posts-card-item">
                    <span class="latest-posts-card-title">{{ $post->title }}</span>
                    <span class="latest-posts-card-excerpt">{{ \Illuminate\Support\Str::limit(strip_tags($post->content), 80) }}</span>
                    <span class="latest-posts-card-date">{{ local_datetime($post->created_at)->format('Y-m-d') }}</span>
                </a>
            @endforeach
        </div>
    @endif
</div>
