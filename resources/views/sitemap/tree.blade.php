{{--
    사이트맵 페이지 전용 재귀 렌더링(최대 3단계) — partials.layout.menu-items/mobile-menu-items와
    달리 hidden_from_header인 메뉴도 그대로 보여준다. GNB에서만 숨긴 것이지 실제로는 접근 가능한
    페이지이므로(예: 마이페이지 그룹), 전체 구조를 보여주는 사이트맵에는 빠짐없이 나와야 한다.
--}}
@foreach ($items as $item)
    @php $hasChildren = ! empty($item['children']); @endphp
    <li>
        <a href="{{ $item['url'] }}"
           target="{{ $item['target'] }}"
           @if ($item['locked']) class="is-locked" title="{{ __('로그인 또는 등급 상향 후 이용 가능합니다') }}" @endif
        >{{ $item['title'] }}</a>

        @if ($hasChildren)
            <ul>
                @include('sitemap.tree', ['items' => $item['children']])
            </ul>
        @endif
    </li>
@endforeach
