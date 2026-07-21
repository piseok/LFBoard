{{--
    모바일 전체메뉴(슬라이드 패널) 아코디언 렌더링 — 최대 3단계까지 재귀.
    하위 메뉴가 있으면 <button>(아코디언 토글, 논클릭)로, 없으면 <a>(실제 이동 링크)로 렌더링한다.
    public/js/frontend.js 하단의 accordion()가 button.nextElementSibling(자신의 <ul>)을 토글한다.
--}}
@php $activeIds = $activeIds ?? []; @endphp
@foreach ($items as $item)
    @continue($item['hidden_from_header'] ?? false)
    @php
        $isActive = in_array($item['id'], $activeIds, true);
        $hasChildren = ! empty($item['children']);
    @endphp
    <li class="{{ $isActive ? 'active' : '' }}">
        @if ($hasChildren)
            <button type="button" aria-expanded="{{ $isActive ? 'true' : 'false' }}">
                {{ $item['title'] }}
            </button>
            <ul style="{{ $isActive ? 'display:block;' : '' }}">
                @include('partials.layout.mobile-menu-items', ['items' => $item['children'], 'activeIds' => $activeIds])
            </ul>
        @else
            <a href="{{ $item['url'] }}"
               target="{{ $item['target'] }}"
               class="{{ $item['locked'] ? 'is-locked' : '' }}"
               @if ($isActive) aria-current="page" @endif
               @if ($item['locked']) title="{{ __('로그인 또는 등급 상향 후 이용 가능합니다') }}" @endif
            >{{ $item['title'] }}@if ($item['locked'])<svg class="menu-lock-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="5" y="11" width="14" height="9" rx="1.5"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>@endif</a>
        @endif
    </li>
@endforeach
