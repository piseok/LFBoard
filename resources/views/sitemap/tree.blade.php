{{--
    사이트맵 페이지 전용 재귀 렌더링(최대 3단계) — partials.layout.menu-items/mobile-menu-items와
    동일하게 hidden_from_header인 메뉴는 숨긴다(사용자 확정 사항: 사이트맵도 상단메뉴와 똑같이
    보여야 함). 예: 마이페이지 그룹은 헤더에 이미 별도 진입 링크가 있어 GNB/사이트맵 모두에서 숨김.
--}}
@foreach ($items as $item)
    @continue($item['hidden_from_header'] ?? false)
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
