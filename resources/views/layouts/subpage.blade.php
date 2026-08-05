{{--
    서브페이지 공용 레이아웃 — 브레드크럼 + content-layout(로컬 네브 + 본문) 구조를 한 곳에서만
    관리한다. 이 구조를 쓰는 화면이 늘어날수록(게시판/문의/페이지/정책 등) 파일마다 반복해서
    넣는 대신 여기 한 번만 고치면 전체에 반영된다.

    사용법:
        @extends('layouts.subpage')
        @section('subcontent')
            <x-sub-header title="...">
                <x-slot:nav>...</x-slot:nav> // 필요할 때만
            </x-sub-header>
            ...
        @endsection

    로컬 네브가 기본(MenuService 기반 partials.shared.local-nav)과 다르면 'local-nav' 섹션을
    오버라이드한다(예: 마이페이지의 정보수정/비밀번호 변경 탭):
        @section('local-nav')
            @include('partials.mypage.local-nav')
        @endsection

    2026-08: 브레드크럼 앞에 브랜치 레벨 제목(예: "회사소개")을 담은 .sub-hero 배너를 추가했다
    (corporate/hospital 디자인 레퍼런스 공통 DNA — "히어로 + 서브내비/브레드크럼" 골격, 지금까지
    이 프로젝트 서브페이지에 없던 "히어로 배너" 자체가 빠져 있던 갭이었음). local-nav.blade.php가
    이미 계산하는 MenuService::getActiveBranch()를 여기서 한 번 더 조회하는데, local-nav를
    'local-nav' 섹션으로 완전히 오버라이드하는 화면(마이페이지 등)과 쿼리 구조가 엮이지 않도록
    일부러 별도로 재조회한다(쿼리 1회 중복이 리팩터링보다 저렴하다는 판단). 현재 경로가 메뉴
    트리에 없는 화면(사이트맵/비밀번호 확인 등)은 top이 없어 히어로 없이 브레드크럼부터
    시작한다(local-nav.blade.php가 하위 메뉴 없으면 <nav> 자체를 안 그리는 것과 동일한 패턴).
--}}
@extends('layouts.app')

@section('content')
    @php
        $subHeroTop = app(\App\Services\MenuService::class)
            ->getActiveBranch(request()->path(), auth()->user()?->level ?? \App\Models\User::LEVEL_GUEST)['top'];
    @endphp
    @if ($subHeroTop)
        <section class="sub-hero">
            <h1 class="sub-hero__title">{{ $subHeroTop['title'] }}</h1>
        </section>
    @endif
    @include('partials.shared.breadcrumb')
    <div class="content-layout">
        @hasSection('local-nav')
            @yield('local-nav')
        @else
            @include('partials.shared.local-nav')
        @endif
        <div class="content-main">
            @yield('subcontent')
        </div>
    </div>
@endsection
