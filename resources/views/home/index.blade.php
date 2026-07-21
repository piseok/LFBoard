@extends('layouts.app')

@section('content')
    @if ($banners->isNotEmpty())
        {{-- 히어로 비주얼 — 배경 이미지 슬라이드 + 캡션 + 하단 퀵링크바(카운터/화살표/일시정지 포함).
             캡션은 배너 관리의 "이미지 위 텍스트"(Repeater, 줄 수 자유)에서 나온다 — 각 줄은
             hero-caption-line-N 클래스로 렌더링되니 CSS에서 줄 번호별로 색상/크기를 다르게
             지정하면 된다. html_content와 마찬가지로 신뢰된 관리자 입력이라 이스케이프하지
             않고 그대로 출력 — <span>/<em> 등 태그를 직접 넣을 수 있다. 줄바꿈은 nl2br로 반영. --}}
        <section class="hero-visual">
            <x-slider pagination="fraction" :pagination-custom="true" :autoplay-toggle="false" :arrows="false" aria-label="{{ __('메인 비주얼') }}">
                @foreach ($banners as $banner)
                    <div class="swiper-slide hero-slide">
                        @if ($banner->content_type === 'html')
                            {{-- 스타일은 사이트 CSS에서 별도로 입히는 걸 전제로 HTML을 그대로 출력. --}}
                            {!! $banner->html_content !!}
                        @else
                            <img src="{{ url($banner->image_path) }}" class="hero-bg" alt="{{ $banner->alt_text }}">
                            @if (! empty($banner->captions))
                                <div class="hero-caption">
                                    @foreach ($banner->captions as $index => $line)
                                        <p class="hero-caption-line hero-caption-line-{{ $index + 1 }}">{!! nl2br($line['text'] ?? '') !!}</p>
                                    @endforeach
                                </div>
                            @endif
                        @endif
                        {{-- link_url이 없는 배너는 클릭 링크를 만들지 않는다(컨트롤러의 홈 이동 폴백 방지). --}}
                        @if ($banner->link_url)
                            <a href="{{ front_route('banner.click', $banner) }}" target="{{ $banner->link_target }}" class="hero-slide-link" aria-label="{{ $banner->title }}"></a>
                        @endif
                    </div>
                @endforeach

                <x-slot:overlay>
                    <div class="hero-bottom-bar">
                        {{-- 예시 퀵링크 4개 — 실제 서비스에 맞는 메뉴/URL로 바꿔서 쓰면 됨(범용 문구로만 채워둠). --}}
                        <ul class="hero-quick-links">
                            <li><a href="#">{{ __('회사 소개') }}</a></li>
                            <li><a href="#">{{ __('사업 안내') }}</a></li>
                            <li><a href="#">{{ __('공지사항') }}</a></li>
                            <li><a href="#">{{ __('오시는 길') }}</a></li>
                        </ul>
                        <div class="hero-controls">
                            <span class="lf-slider-pagination"></span>
                            <button type="button" class="lf-slider-prev" aria-label="{{ __('이전 슬라이드') }}"></button>
                            <button type="button" class="lf-slider-next" aria-label="{{ __('다음 슬라이드') }}"></button>
                            <button type="button" class="lf-slider-toggle" data-state="playing" aria-label="{{ __('일시정지') }}"></button>
                        </div>
                    </div>
                </x-slot:overlay>
            </x-slider>
        </section>
    @endif

    <h1 class="page-title">{{ app(\App\Services\SiteSettingService::class)->getLocalized('site_name', default: config('app.name')) }}</h1>
    <p>{{ app(\App\Services\SiteSettingService::class)->get('site_description') }}</p>

    @include('partials.home.site-search')
@endsection
