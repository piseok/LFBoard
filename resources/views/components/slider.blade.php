{{--
    ============================================================================
     범용 반응형 슬라이드 컴포넌트 (Swiper 14 래핑) — 배너/최신글 위젯/그 외 어디서든 재사용.
    ============================================================================

    ----------------------------------------------------------------------------
    [모드 A] 오버레이를 안 쓰는 일반적인 경우 (대부분 여기에 해당)
    ----------------------------------------------------------------------------
    화살표/점(또는 숫자·fraction)/일시정지 버튼을 전부 컴포넌트가 알아서 기본 위치에
    그려준다. 아래처럼 그냥 쓰면 끝 — overlay, autoplay-toggle, pagination-custom 같은
    건 신경 쓸 필요 없음(전부 기본값으로 충분).

    (A-1) 이미지 목록을 그대로 전달:
        <x-slider :items="$banners" />
        <x-slider :items="$footerBanners" :arrows="false" pagination="none" />

    (A-2) 직접 마크업(슬라이드 카드가 이미지+링크보다 복잡할 때, 게시판 카드 슬라이드 등):
        <x-slider :slides-per-view="[3,2,1]" pagination="numbers">
            @foreach ($posts as $post)
                <div class="swiper-slide">...커스텀 카드...</div>
            @endforeach
        </x-slider>

    items 전달 시 각 항목은 image_path/link_url/link_target/alt_text/title 속성을 쓴다
    (Banner 모델을 매핑 없이 그대로 넘길 수 있음 — 객체/배열 둘 다 지원). content_type이
    'html'인 항목은 image_path 대신 html_content를 그대로 출력한다(Banner의 이미지/HTML
    콘텐츠 타입 전환 기능과 연동).

    pagination 옵션(A/B 모드 공통): 'dots'(기본, 원형 점) | 'numbers'(클릭 가능한 숫자
    버튼 1 2 3) | 'fraction'("01 / 03"처럼 현재/전체 카운터) | 'none'(표시 안 함).

    ----------------------------------------------------------------------------
    [모드 B] 오버레이를 쓰는 경우 — "화살표/카운터/일시정지를 컴포넌트 기본 위치가 아니라
    내가 만든 커스텀 레이아웃(예: 슬라이드 밑에 항상 떠 있는 퀵링크 바) 안에 넣고 싶을 때"만
    필요하다. 히어로 비주얼(대학교 메인 배경 슬라이드 + 하단 바 예시)이 대표적인 경우.
    ----------------------------------------------------------------------------
    일반적인 A 모드와 달리, B 모드는 아래 3가지를 "같이" 써야 정상 동작한다(하나라도
    빠지면 버튼이 중복되거나 안 보인다):

        1) :arrows="false"          → 컴포넌트가 자기 화살표를 안 그림(내가 직접 그릴 거라서)
        2) :autoplay-toggle="false" → 컴포넌트가 자기 일시정지 버튼을 안 그림(위와 동일 이유)
        3) :pagination-custom="true" → 컴포넌트가 자기 페이지네이션 div를 안 그림.
           단, pagination="fraction"/"numbers" 값 자체는 그대로 둬야 한다 — JS 동작
           방식(어떤 스타일로 셀지)은 이 값으로 정해지고, pagination-custom은 오직
           "그 결과를 어디에 그릴지(내가 직접 배치)"만 바꾼다.

    이렇게 3개를 다 끄고 나면, 화살표/카운터/일시정지 버튼이 어디에도 안 보이는 상태가
    된다 — 이제 <x-slot:overlay> 안에 내가 원하는 위치로 직접 그려주면 된다. 이때
    클래스 이름은 반드시 아래 3개 그대로 써야 한다(slider-init.js가 이 클래스명으로
    .lf-slider 전체 범위를 querySelector로 찾아서 배선하기 때문 — 이름을 바꾸면 그
    버튼/카운터는 아무 동작도 안 함):
        .lf-slider-prev / .lf-slider-next / .lf-slider-toggle / .lf-slider-pagination

    예시:
        <x-slider pagination="fraction" :pagination-custom="true" :autoplay-toggle="false" :arrows="false">
            @foreach ($banners as $banner)
                <div class="swiper-slide">...</div>
            @endforeach

            (참고: overlay 슬롯 안의 이 내용은 .swiper-wrapper 밖(형제)으로 렌더링되므로
             Swiper가 슬라이드로 착각해 개수/이동 계산이 틀어지지 않는다.)
            <x-slot:overlay>
                <div class="hero-bottom-bar">
                    <span class="lf-slider-pagination"></span>
                    <button type="button" class="lf-slider-prev">‹</button>
                    <button type="button" class="lf-slider-next">›</button>
                    <button type="button" class="lf-slider-toggle" data-state="playing"></button>
                </div>
            </x-slot:overlay>
        </x-slider>

    실제 적용 예: resources/views/home/index.blade.php의 히어로 비주얼 섹션 참고.

    ----------------------------------------------------------------------------
    반응형/자동재생/페이지네이션/키보드/스와이프 동작은 public/js/slider-init.js가
    위 data-* 속성을 읽어 Swiper 인스턴스를 생성하며 처리한다(모드 A/B 공통, 이 파일은
    안 건드려도 됨). Swiper 본체(swiper-bundle.min.js/css)는 public/js/vendor/swiper,
    public/css/vendor/swiper에 벤더링되어 있고, 이 컴포넌트가 쓰는 래퍼/버튼/숫자
    페이지네이션 스타일은 별도 파일 public/css/slider.css에 있다(frontend.css는
    건드리지 않음 — 슬라이드 안쪽 콘텐츠 전용 스타일만 frontend.css에 둔다).
--}}
@props([
    'items' => null,
    'id' => null,
    'autoplay' => true,
    'autoplayToggle' => true,
    'interval' => 5000,
    'loop' => true,
    'arrows' => true,
    'pagination' => 'dots',
    'paginationCustom' => false,
    'slidesPerView' => [1, 1, 1],
    'gap' => 16,
    'ariaLabel' => null,
])

@php
    $sliderId = $id ?? 'slider-'.\Illuminate\Support\Str::random(8);

    $itemValue = fn ($item, string $key) => is_array($item) ? ($item[$key] ?? null) : ($item->{$key} ?? null);

    $slideCount = $items !== null ? (is_countable($items) ? count($items) : 0) : null;
    $showNav = $arrows && ($slideCount === null || $slideCount > 1);
    // paginationCustom=true면 data-pagination 값(예: fraction)은 그대로 JS에 전달하되,
    // 컴포넌트 자체 페이지네이션 div는 렌더링하지 않는다 — overlay 슬롯 안에 직접 만든
    // .lf-slider-pagination 요소를 쓸 때(히어로 비주얼처럼 하단 바 안에 넣고 싶을 때) 사용.
    $showPagination = ! $paginationCustom && $pagination !== 'none' && ($slideCount === null || $slideCount > 1);
@endphp

<div
    id="{{ $sliderId }}"
    {{ $attributes->class(['lf-slider', 'swiper']) }}
    data-slider
    data-autoplay="{{ $autoplay ? 'true' : 'false' }}"
    data-interval="{{ $interval }}"
    data-loop="{{ $loop ? 'true' : 'false' }}"
    data-pagination="{{ $pagination }}"
    data-slides-desktop="{{ $slidesPerView[0] }}"
    data-slides-tablet="{{ $slidesPerView[1] }}"
    data-slides-mobile="{{ $slidesPerView[2] }}"
    data-gap="{{ $gap }}"
    role="region"
    aria-label="{{ $ariaLabel ?? __('슬라이드') }}"
>
    <div class="swiper-wrapper">
        @if ($items !== null)
            @foreach ($items as $item)
                @php
                    // content_type이 'html'인 항목(예: Banner)은 image_path 대신 html_content를
                    // 그대로 출력한다 — 스타일은 사이트 CSS에서 별도로 입히는 걸 전제로 함.
                    $isHtml = $itemValue($item, 'content_type') === 'html';
                    $image = $itemValue($item, 'image_path');
                    $url = $itemValue($item, 'link_url');
                    $target = $itemValue($item, 'link_target') ?: '_self';
                    $alt = $itemValue($item, 'alt_text') ?: $itemValue($item, 'title');
                    // 배너 관리의 "이미지 위 텍스트"(Repeater, [['text' => ...], ...]) — image 타입에서만
                    // 의미가 있다(BannerResource 폼도 content_type === 'image'일 때만 노출). home/index.blade.php의
                    // 히어로 렌더링과 동일한 hero-caption/hero-caption-line-N 마크업을 여기서도 기본으로
                    // 그려서, :items 숏핸드로 슬라이더를 쓰는 모든 곳(footer, ja/home 등)에서 캡션이 빠지지 않게 한다.
                    $captions = ! $isHtml ? ($itemValue($item, 'captions') ?: []) : [];
                @endphp
                <div class="swiper-slide">
                    @if ($isHtml)
                        {!! $itemValue($item, 'html_content') !!}
                    @elseif ($url)
                        <a href="{{ $url }}" target="{{ $target }}">
                            <img src="{{ url($image) }}" alt="{{ $alt }}">
                        </a>
                    @else
                        <img src="{{ url($image) }}" alt="{{ $alt }}">
                    @endif
                    @if (! empty($captions))
                        {{-- 신뢰된 관리자 입력이라 이스케이프하지 않고 그대로 출력(줄바꿈은 nl2br) —
                             home/index.blade.php 히어로 캡션과 동일한 규칙. --}}
                        <div class="hero-caption">
                            @foreach ($captions as $index => $line)
                                <p class="hero-caption-line hero-caption-line-{{ $index + 1 }}">{!! nl2br(is_array($line) ? ($line['text'] ?? '') : $line) !!}</p>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        @else
            {{ $slot }}
        @endif
    </div>

    {{-- .swiper-wrapper 밖(형제)에 렌더링 — Swiper가 .swiper-wrapper의 모든 자식을 슬라이드
         후보로 계산하기 때문에, 슬라이드가 아닌 오버레이 콘텐츠는 반드시 이 바깥에 둬야 한다. --}}
    @isset($overlay)
        {{ $overlay }}
    @endisset

    @if ($showPagination)
        <div class="lf-slider-pagination swiper-pagination"></div>
    @endif

    @if ($showNav)
        <button type="button" class="lf-slider-nav lf-slider-prev" aria-label="{{ __('이전 슬라이드') }}" @isset($prevIcon) data-custom-icon @endisset>@isset($prevIcon){{ $prevIcon }}@endisset</button>
        <button type="button" class="lf-slider-nav lf-slider-next" aria-label="{{ __('다음 슬라이드') }}" @isset($nextIcon) data-custom-icon @endisset>@isset($nextIcon){{ $nextIcon }}@endisset</button>
    @endif

    @if ($autoplay && $autoplayToggle)
        <button type="button" class="lf-slider-toggle" data-state="playing" aria-label="{{ __('일시정지') }}"></button>
    @endif
</div>
