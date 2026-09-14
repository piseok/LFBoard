{{--
    작업사례(node 55:140) — 채팅 지시(2026-09-14, "메인에는 내가 지정한 타입만 나오게 하고 각
    타입별로 버튼이 나오고 전체가 있어, 스와이프 나오게 하고싶어") — 게시판이 아니라 배너관리
    그룹 키 기준 탭+슬라이드. $bannerTabs는 FrontController::buildBannerTabs()가 SiteSettings의
    home_banner_tab_groups(쉼표구분 배너 group_key 목록)를 읽어 만든다.

    탭 전환(활성 클래스 + inert)은 public/js/frontend.js의 [data-cases-tabs] 블록이 담당하고,
    각 패널의 <x-slider>는 기존 slider-init.js가 그대로 초기화한다(숨겨진 패널(main.css의
    .main-cases-panel — display:none)에서 초기화된 슬라이더는 활성화 시점에 swiper.update()로
    재계산 — frontend.js 참고). 스타일은 main.css의 "작업사례(node 55:140)" 블록.
--}}
@php
    $siteSettings = app(\App\Services\SiteSettingService::class);
@endphp
<section class="main-cases" data-cases-tabs>
    <div class="main-cases-head">
        @if ($desc = $siteSettings->get('home_banner_tabs_desc'))
            <p class="main-cases-desc">{{ $desc }}</p>
        @endif
        <h2 class="main-cases-title">{{ $siteSettings->get('home_banner_tabs_title', '작업사례') }}</h2>

        <div class="main-cases-tags" role="tablist" aria-label="{{ __('작업사례 분류') }}">
            @foreach ($bannerTabs as $index => $tab)
                <button
                    type="button"
                    id="main-cases-tab-{{ $tab['key'] }}"
                    class="main-cases-tab @if ($index === 0) is-active @endif"
                    role="tab"
                    aria-selected="{{ $index === 0 ? 'true' : 'false' }}"
                    aria-controls="main-cases-panel-{{ $tab['key'] }}"
                    data-cases-tab="{{ $tab['key'] }}"
                >{{ $tab['name'] }}</button>
            @endforeach
        </div>
    </div>

    <div class="main-cases-panels">
        @foreach ($bannerTabs as $index => $tab)
            <div
                id="main-cases-panel-{{ $tab['key'] }}"
                class="main-cases-panel @if ($index === 0) is-active @endif"
                role="tabpanel"
                aria-labelledby="main-cases-tab-{{ $tab['key'] }}"
                data-cases-panel="{{ $tab['key'] }}"
                @if ($index !== 0) inert @endif
            >
                <div class="main-cases-gallery">
                    @if ($tab['banners']->isEmpty())
                        <p class="main-cases-empty">{{ __('등록된 배너가 없습니다.') }}</p>
                    @else
                        <x-slider :slides-per-view="[3, 2, 1]" :gap="24" pagination="none" :autoplay="true" :loop="true" :aria-label="$tab['name']">
                            @foreach ($tab['banners'] as $banner)
                                <div class="swiper-slide">
                                    {{-- link_url이 없는 배너는 클릭 링크를 만들지 않는다(home/index.blade.php 히어로와 동일 규칙). --}}
                                    @if ($banner->link_url)
                                        <a href="{{ front_route('banner.click', $banner) }}" target="{{ $banner->link_target }}" class="main-cases-card" aria-label="{{ $banner->title }}">
                                            @include('partials.home.banner-tabs-card-content', ['banner' => $banner])
                                        </a>
                                    @else
                                        <div class="main-cases-card">
                                            @include('partials.home.banner-tabs-card-content', ['banner' => $banner])
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </x-slider>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</section>
