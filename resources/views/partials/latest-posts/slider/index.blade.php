{{-- 슬라이드형 스킨 — 썸네일 + 제목 + 작성일을 <x-slider>로 넘기며 한 칸씩 넘어가는 캐러셀로 보여준다. --}}
<div class="latest-posts latest-posts-slider">
    <div class="latest-posts-header">
        <h2 class="latest-posts-title">{{ $board->name }}</h2>
        <a href="{{ front_route('board.index', $board->slug) }}" class="latest-posts-more">{{ __('더보기') }}</a>
    </div>

    @if ($posts->isEmpty())
        <p class="latest-posts-empty">{{ __('등록된 게시글이 없습니다.') }}</p>
    @else
        <x-slider :slides-per-view="[3, 2, 1]" pagination="numbers" :aria-label="$board->name">
            @foreach ($posts as $post)
                @php $thumb = $post->files->first(fn ($f) => str_starts_with((string) $f->mime_type, 'image/')); @endphp
                <div class="swiper-slide">
                    <a href="{{ front_route('board.show', ['slug' => $board->slug, 'id' => $post->id]) }}" class="latest-posts-slider-item">
                        @if ($thumb)
                            <img src="{{ url($thumb->file_path) }}" alt="">
                        @else
                            <div class="latest-posts-slider-noimage">{{ __('No Image') }}</div>
                        @endif
                        <span class="latest-posts-slider-title">{{ $post->title }}</span>
                        <span class="latest-posts-slider-date">{{ local_datetime($post->created_at)->format('Y-m-d') }}</span>
                    </a>
                </div>
            @endforeach
        </x-slider>
    @endif
</div>
