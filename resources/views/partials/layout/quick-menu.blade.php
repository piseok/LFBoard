{{-- TOP 버튼은 "퀵메뉴 표시" 설정과 무관하게 항상 노출한다 — 그 설정은 상담 아이콘만 켜고 끈다. --}}
<nav class="quick-menu" aria-label="{{ __('퀵메뉴') }}">
    @if (app(\App\Services\SiteSettingService::class)->get('show_quick_menu', '1') === '1')
        <a href="{{ front_route('inquiry.create', ['type' => 'quick']) }}">{!! __('빠른<br>상담') !!}</a>
    @endif
    <a href="#top" aria-label="{{ __('맨 위로 이동') }}" class="scroll-top-btn">TOP</a>
</nav>
