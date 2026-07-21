{{-- 기본 스킨 — 제목 + 작성일 한 줄 리스트. $posts, $board는 latest-posts 컴포넌트가 전달한다. --}}
<div class="latest-posts latest-posts-list">
    <div class="latest-posts-header">
        <h2 class="latest-posts-title">{{ $board->name }}</h2>
        <a href="{{ front_route('board.index', $board->slug) }}" class="latest-posts-more">{{ __('더보기') }}</a>
    </div>

    @if ($posts->isEmpty())
        <p class="latest-posts-empty">{{ __('등록된 게시글이 없습니다.') }}</p>
    @else
        <ul class="latest-posts-items">
            @foreach ($posts as $post)
                <li>
                    <a href="{{ front_route('board.show', ['slug' => $board->slug, 'id' => $post->id]) }}">
                        <span class="latest-posts-item-title">{{ $post->title }}</span>
                        <span class="latest-posts-item-date">{{ local_datetime($post->created_at)->format('Y-m-d') }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</div>
