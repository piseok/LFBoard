{{-- 목록 테이블의 "모집기간" 전용 컬럼 — partials.shared.recruitment-status(제목 아래 인라인 표시용)와
     달리 별도 <td>에 배지+기간만 간결하게 보여준다(시:분 없이 날짜만). --}}
@php
    $recruitmentStatus = $post->recruitmentStatus();
@endphp

@if ($recruitmentStatus)
    <span class="badge badge-{{ ['예정' => 'gray', '기간중' => 'success', '마감' => 'danger'][$recruitmentStatus] }}">{{ __($recruitmentStatus) }}</span>
    @if ($post->recruitment_start_at || $post->recruitment_end_at)
        <br>
        <span class="board-recruitment-period">
            {{ $post->recruitment_start_at ? local_datetime($post->recruitment_start_at)->format('Y-m-d') : __('상시') }}
            ~
            {{ $post->recruitment_end_at ? local_datetime($post->recruitment_end_at)->format('Y-m-d') : __('상시') }}
        </span>
    @endif
@else
    <span class="post-meta">-</span>
@endif
