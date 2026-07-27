{{-- 방문자(프론트) 화면에서 발생한 에러용 — 사이트 헤더/푸터를 그대로 써서 "사이트를 벗어난
    것 같은" 느낌 없이 자연스럽게 안내합니다. --}}
@php
    $siteSettings = app(\App\Services\SiteSettingService::class);
    $siteName = $siteSettings->getLocalized('site_name', default: config('app.name'));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} | {{ $siteName }}</title>
    <link rel="preload" href="{{ asset('fonts/vendor/pretendard/PretendardVariable.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset('css/vendor/pretendard/pretendard.css') }}">
    <link rel="stylesheet" href="{{ asset('css/frontend.css') }}">
    <link rel="stylesheet" href="{{ asset('css/sub.css') }}">
    <link rel="stylesheet" href="{{ asset('css/board.css') }}">
</head>
<body>
    @include('partials.layout.header')

    <main id="main-content" class="main-content">
        <div class="container" style="text-align:center; padding: 80px 20px;">
            <p style="font-size:0.9rem; color:var(--color-text-muted); letter-spacing:0.05em; margin:0;">{{ $code }}</p>
            <h1 class="page-title" style="margin-top:8px;">{{ $title }}</h1>
            <p style="color:var(--color-text-muted); margin-bottom:24px;">{{ $message }}</p>
            <a href="{{ front_route('home') }}" class="btn btn-primary">{{ __('홈으로 가기') }}</a>
        </div>
    </main>

    @include('partials.layout.footer')
</body>
</html>
