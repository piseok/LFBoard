{{--
    데스크톱 상단 메뉴(hover 드롭다운) 재귀 렌더링 (최대 3단계).
    $items: MenuService::getTree() 형식
    $activeIds: 현재 위치 강조용 메뉴 ID 목록(선택)
    모바일 전체메뉴 아코디언은 partials.mobile-menu-items가 별도로 담당한다.
--}}
@php $activeIds = $activeIds ?? []; @endphp
@foreach ($items as $item)
    @continue($item['hidden_from_header'] ?? false)
    @php
        $isActive = in_array($item['id'], $activeIds, true);
        $hasChildren = ! empty($item['children']);
        $linkClass = trim(($isActive ? 'is-active' : '').' '.($item['locked'] ? 'is-locked' : ''));
    @endphp
    <li>
        <a href="{{ $item['url'] }}"
           target="{{ $item['target'] }}"
           class="{{ $linkClass }}"
           @if ($isActive) aria-current="page" @endif
           @if ($item['locked']) title="{{ __('로그인 또는 등급 상향 후 이용 가능합니다') }}" @endif
           @if ($item['type'] === 'none') role="button" aria-haspopup="true" aria-expanded="false" @endif
        >{{ $item['title'] }}@if ($item['locked'])<svg class="menu-lock-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="5" y="11" width="14" height="9" rx="1.5"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>@endif</a>

        @if ($hasChildren)
            <ul class="depth2">
                @include('partials.layout.menu-items', ['items' => $item['children'], 'activeIds' => $activeIds])
            </ul>
        @endif
    </li>
@endforeach
