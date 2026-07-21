@php
    $menuService = app(\App\Services\MenuService::class);
    $userLevel = auth()->user()?->level ?? \App\Models\User::LEVEL_GUEST;
    $menuTree = $menuService->getTree($userLevel);
    $headerActiveIds = $menuService->getActiveBranch(request()->path(), $userLevel)['activeIds'];
    $siteName = app(\App\Services\SiteSettingService::class)->getLocalized('site_name', default: config('app.name'));
    $siteLogo = app(\App\Services\SiteSettingService::class)->get('site_logo');
@endphp
<div class="dim"></div>

<header class="header">
    <div class="container">
        <p class="logo">
            <a href="{{ front_route('home') }}" aria-label="{{ $siteName }} {{ __('홈으로 이동') }}">
                @if ($siteLogo)
                    <img src="{{ url($siteLogo) }}" alt="{{ $siteName }}">
                @else
                    {{ $siteName }}
                @endif
            </a>
        </p>

        <nav class="gnb" aria-label="{{ __('주 메뉴') }}">
            <ul class="depth1">
                @include('partials.layout.menu-items', ['items' => $menuTree, 'activeIds' => $headerActiveIds])
            </ul>
        </nav>

        <div class="header-actions">
            @include('partials.layout.language-switcher')

            @guest
                <a href="{{ front_route('login') }}" class="btn-auth">{{ __('로그인') }}</a>
                <a href="{{ front_route('register') }}" class="btn-auth btn-auth-primary">{{ __('회원가입') }}</a>
            @else
                @if (auth()->user()->isAdmin())
                    <a href="{{ url('/'.config('app.admin_path', 'admin')) }}" class="btn-auth btn-auth-primary">{{ __('관리자 바로가기') }}</a>
                @else
                    <a href="{{ front_route('mypage') }}" class="btn-auth">{{ __(':name님', ['name' => auth()->user()->name]) }}</a>
                @endif
                <form method="POST" action="{{ front_route('logout') }}" class="logout-form">
                    @csrf
                    <button type="submit" class="btn-auth">{{ __('로그아웃') }}</button>
                </form>
            @endguest
        </div>

        <button type="button" class="mobile-btn" aria-label="{{ __('전체 메뉴 열기') }}" aria-expanded="false" aria-controls="mobile-nav">
            <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 30 30" fill="none" aria-hidden="true">
                <circle cx="15" cy="15" r="15" fill="#F26521"/>
                <path d="M9 19H21V17.6667H9V19ZM9 15.6667H21V14.3333H9V15.6667ZM9 11V12.3333H21V11H9Z" fill="white"/>
            </svg>
        </button>
    </div>
</header>

<nav class="mobile-menu" id="mobile-nav" role="dialog" aria-modal="true" aria-label="{{ __('전체 메뉴') }}">
    <div class="mobile-top">
        <div class="mobile-top-start">
            <p class="mobile-menu-logo">
                <a href="{{ front_route('home') }}" aria-label="{{ $siteName }} {{ __('홈으로 이동') }}">
                    @if ($siteLogo)
                        <img src="{{ url($siteLogo) }}" alt="{{ $siteName }}">
                    @else
                        {{ $siteName }}
                    @endif
                </a>
            </p>
            <div class="mobile-auth">
                @guest
                    <a href="{{ front_route('login') }}">{{ __('로그인') }}</a>
                    <a href="{{ front_route('register') }}">{{ __('회원가입') }}</a>
                @else
                    @if (auth()->user()->isAdmin())
                        <a href="{{ url('/'.config('app.admin_path', 'admin')) }}">{{ __('관리자 바로가기') }}</a>
                    @else
                        <a href="{{ front_route('mypage') }}">{{ __(':name님', ['name' => auth()->user()->name]) }}</a>
                    @endif
                    <form method="POST" action="{{ front_route('logout') }}" class="logout-form">
                        @csrf
                        <button type="submit">{{ __('로그아웃') }}</button>
                    </form>
                @endguest
            </div>
        </div>
        <button type="button" class="mobile-close" aria-label="{{ __('메뉴 닫기') }}">
            <svg xmlns="http://www.w3.org/2000/svg" width="45" height="45" viewBox="0 0 45 45" fill="none" aria-hidden="true">
                <circle cx="22.5" cy="22.5" r="22.5" fill="#F26521"/>
                <path d="M30 16.5107L28.4893 15L22.5 20.9893L16.5107 15L15 16.5107L20.9893 22.5L15 28.4893L16.5107 30L22.5 24.0107L28.4893 30L30 28.4893L24.0107 22.5L30 16.5107Z" fill="white"/>
            </svg>
        </button>
    </div>

    <div class="mobile-lang">
        @include('partials.layout.language-switcher')
    </div>

    <ul>
        @include('partials.layout.mobile-menu-items', ['items' => $menuTree, 'activeIds' => $headerActiveIds])
    </ul>
</nav>
