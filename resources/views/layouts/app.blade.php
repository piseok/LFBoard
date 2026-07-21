@php
    /** @var \App\Services\SiteSettingService $siteSettings */
    $siteSettings = app(\App\Services\SiteSettingService::class);
    $siteName = $siteSettings->getLocalized('site_name', default: config('app.name'));
    $metaTitle = trim(($pageTitle ?? '').(($pageTitle ?? '') ? $siteSettings->get('meta_title_separator', ' | ') : '').$siteName);
    $metaDescription = $pageDescription ?? $siteSettings->get('site_description', '');
    $metaKeywords = $pageKeywords ?? $siteSettings->get('site_keywords', '');
    $ogImage = $pageOgImage ?? $siteSettings->get('site_logo', '');

    // CSP(script-src)가 nonce 없는 인라인 스크립트를 차단하므로, 관리자가 붙여넣은 서드파티 스니펫(<script> 포함)에
    // 이번 요청의 nonce를 자동으로 부여해 정상 동작하게 한다.
    $applyNonce = fn (string $html): string => $html !== ''
        ? preg_replace('/<script\b/i', '<script nonce="'.($cspNonce ?? '').'"', $html)
        : $html;
    $headScripts = $applyNonce($siteSettings->getLocalized('head_scripts', default: ''));
    $bodyScripts = $applyNonce($siteSettings->getLocalized('body_scripts', default: ''));

    $cookieConsent = app(\App\Services\CookieConsentService::class);
    $analyticsAllowed = $cookieConsent->analyticsAllowed(request());

    // 17-1번의 언어 전환 버튼(LocaleSwitchService)과 같은 "같은 slug면 그 언어 화면, 없으면 그 언어 홈"
    // 로직을 그대로 재사용 — 검색엔진에 같은 콘텐츠의 언어별 대체 URL을 알려주는 hreflang 태그.
    $localeLinks = app(\App\Services\LocaleSwitchService::class)->links(request());
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $metaTitle ?: $siteName }}</title>
    @if ($metaDescription)
        <meta name="description" content="{{ $metaDescription }}">
    @endif
    @if ($metaKeywords)
        <meta name="keywords" content="{{ $metaKeywords }}">
    @endif

    <meta property="og:title" content="{{ $metaTitle ?: $siteName }}">
    <meta property="og:site_name" content="{{ $siteName }}">
    @if ($metaDescription)
        <meta property="og:description" content="{{ $metaDescription }}">
    @endif
    @if ($ogImage)
        <meta property="og:image" content="{{ url($ogImage) }}">
    @endif
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">

    @if (count($localeLinks) > 1)
        @foreach ($localeLinks as $link)
            <link rel="alternate" hreflang="{{ $link['code'] }}" href="{{ $link['url'] }}">
        @endforeach
        <link rel="alternate" hreflang="x-default" href="{{ collect($localeLinks)->firstWhere('code', \App\Models\Language::defaultCode())['url'] ?? $localeLinks[0]['url'] }}">
    @endif

    @if ($favicon = $siteSettings->get('site_favicon'))
        <link rel="icon" href="{{ url($favicon) }}">
    @endif

    <link rel="preload" href="{{ asset('fonts/vendor/pretendard/PretendardVariable.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset('css/vendor/pretendard/pretendard.css') }}">
    <link rel="stylesheet" href="{{ asset('css/frontend.css') }}">
    <link rel="stylesheet" href="{{ asset('css/vendor/swiper/swiper-bundle.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/slider.css') }}">
    @if (request()->routeIs('home', '*.home'))
        <link rel="stylesheet" href="{{ asset('css/main.css') }}">
    @else
        {{-- 홈이 아닌 나머지 모든 화면(게시판/문의/마이페이지/검색/인증 등)에서 공통으로 쓰는
             스타일. board.css는 이름과 달리 게시판 전용 클래스만 담고 있지 않고 커뮤니티/검색
             화면도 board-toolbar 등 같은 클래스명을 재사용하므로, 라우트별로 잘게 나누는 대신
             홈이 아닌 모든 화면에 함께 로드해 클래스 재사용에 따른 스타일 누락을 막는다. --}}
        <link rel="stylesheet" href="{{ asset('css/sub.css') }}">
        <link rel="stylesheet" href="{{ asset('css/board.css') }}">
    @endif

    @stack('meta')

    @if (($ga = $siteSettings->getLocalized('google_analytics')) && $analyticsAllowed)
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ $ga }}"></script>
    @endif

    {!! $headScripts !!}
</head>
<body data-ga-id="{{ $analyticsAllowed ? ($ga ?? '') : '' }}">
    <a href="#main-content" class="skip-link">{{ __('본문 바로가기') }}</a>

    @include('partials.layout.header')
    @include('partials.layout.policy-notice-banner')

    <main id="main-content" class="main-content">
        <div class="container">
            @if (session('status'))
                <div class="alert alert-success" role="status">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-error" role="alert">
                    <ul style="margin:0;padding-left:18px;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </div>
    </main>

    @include('partials.layout.footer')
    @include('partials.layout.quick-menu')
    @if (request()->routeIs('home', '*.home'))
        @include('partials.layout.popup')
    @endif
    @if ($cookieConsent->shouldShow(request()))
        @include('partials.layout.cookie-consent', ['message' => $cookieConsent->message()])
    @endif

    <script src="{{ asset('js/frontend.js') }}" defer></script>
    <script src="{{ asset('js/vendor/swiper/swiper-bundle.min.js') }}" defer></script>
    <script src="{{ asset('js/slider-init.js') }}" defer></script>

    {!! $bodyScripts !!}
    @stack('scripts')
</body>
</html>
