{{--
    서브페이지 타이틀 + 선택적 설명 + 선택적 보조 nav를 하나로 묶는 공용 헤더.
    좌측 LNB(partials/shared/local-nav.blade.php)와 짝을 이루는 우측 헤더로,
    지금까지 페이지마다 따로 마크업하던 h1.page-title + post-meta + board-category-filter
    조합을 대체한다. layouts.subpage 기반 화면뿐 아니라 layouts.app을 직접 쓰는 화면(예:
    마이페이지)에서도 그대로 쓸 수 있다 — .content-main 안에 있다고 가정하지 않는다.

    사용법:
        <x-sub-header :title="$board->name" :description="$board->description">
            <x-slot:nav>
                <nav class="board-category-filter">...</nav>
            </x-slot:nav>
        </x-sub-header>

    title 없이 쓰면 기본 슬롯이 <h1> 안에 그대로 렌더된다(리치 콘텐츠가 필요할 때, 예: 임시저장 배지):
        <x-sub-header>
            {{ $post ? __('글 수정') : __('글쓰기') }}
            @if ($post && $post->is_draft)<span class="badge badge-warning">{{ __('임시저장') }}</span>@endif
        </x-sub-header>

    description가 여러 줄/조건부라면 prop 대신 <x-slot:description>을 쓴다.
--}}
@props(['title' => null, 'description' => null])
<header class="sub-header">
    <h1 class="page-title">{{ $title ?? $slot }}</h1>
    @if ($description)
        <p class="post-meta">{{ $description }}</p>
    @endif
    @isset($nav)
        {{ $nav }}
    @endisset
</header>
