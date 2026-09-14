{{--
    채팅 지시(2026-09-14, "메인에는 내가 지정한 타입만 나오게 하고 각 타입별로 버튼이 나오고
    전체가 있어, 스와이프 나오게 하고싶어") — 게시판이 아니라 배너관리 그룹 키 기준 탭+슬라이드.
    $bannerTabs는 FrontController::buildBannerTabs()가 SiteSettings의 home_banner_tab_groups
    (쉼표구분 배너 group_key 목록)를 읽어 만든다. 탭 전환(활성 클래스 + inert)은
    public/js/home-banner-tabs.js가 담당하고, 각 패널의 <x-slider>는 기존 slider-init.js가
    그대로 초기화한다(숨겨진 패널에서 초기화된 슬라이더는 활성화 시점에 swiper.update()로
    재계산 — home-banner-tabs.js 참고).
--}}
<section class="main-banner-tabs" data-banner-tabs>
    <div class="main-banner-tabs-nav" role="tablist" aria-label="{{ __('배너 분류') }}">
        @foreach ($bannerTabs as $index => $tab)
            <button
                type="button"
                id="main-banner-tab-{{ $tab['key'] }}"
                class="main-banner-tab-btn @if ($index === 0) is-active @endif"
                role="tab"
                aria-selected="{{ $index === 0 ? 'true' : 'false' }}"
                aria-controls="main-banner-panel-{{ $tab['key'] }}"
                data-banner-tab-btn="{{ $tab['key'] }}"
            >{{ $tab['name'] }}</button>
        @endforeach
    </div>

    <div class="main-banner-tabs-panels">
        @foreach ($bannerTabs as $index => $tab)
            <div
                id="main-banner-panel-{{ $tab['key'] }}"
                class="main-banner-tab-panel @if ($index === 0) is-active @endif"
                role="tabpanel"
                aria-labelledby="main-banner-tab-{{ $tab['key'] }}"
                data-banner-tab-panel="{{ $tab['key'] }}"
                @if ($index !== 0) inert @endif
            >
                @if ($tab['banners']->isEmpty())
                    <p class="main-banner-tabs-empty">{{ __('등록된 배너가 없습니다.') }}</p>
                @else
                    <x-slider :items="$tab['banners']" :slides-per-view="[3, 2, 1]" :gap="24" pagination="none" :autoplay="true" :loop="true" :aria-label="$tab['name']" />
                @endif
            </div>
        @endforeach
    </div>
</section>
