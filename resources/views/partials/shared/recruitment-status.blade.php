{{-- 게시판이 아니라 게시글(Post) 하나하나의 모집 기간을 표시한다 — 게시판 하나에 채용 공고가
     여러 건 올라오고 각각 마감일이 다른 경우가 많아 게시판 단위가 아닌 글 단위로 상태를 매긴다. --}}
@php
    $recruitmentStatus = $post->recruitmentStatus();
@endphp

@if ($recruitmentStatus)
    <p class="post-meta">
        <span class="badge badge-{{ ['예정' => 'gray', '기간중' => 'success', '마감' => 'danger'][$recruitmentStatus] }}">{{ __($recruitmentStatus) }}</span>
        @if ($post->recruitment_start_at || $post->recruitment_end_at)
            <span>
                {{ $post->recruitment_start_at ? local_datetime($post->recruitment_start_at)->format('Y-m-d H:i') : __('상시') }}
                ~
                {{ $post->recruitment_end_at ? local_datetime($post->recruitment_end_at)->format('Y-m-d H:i') : __('상시') }}
            </span>
        @endif
    </p>
@endif
