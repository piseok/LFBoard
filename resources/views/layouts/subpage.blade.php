{{--
    서브페이지 공용 레이아웃 — 브레드크럼 + content-layout(로컬 네브 + 본문) 구조를 한 곳에서만
    관리한다. 이 구조를 쓰는 화면이 늘어날수록(게시판/문의/페이지/정책 등) 파일마다 반복해서
    넣는 대신 여기 한 번만 고치면 전체에 반영된다.

    사용법:
        @extends('layouts.subpage')
        @section('subcontent')
            <h1 class="page-title">...</h1>
            ...
        @endsection

    로컬 네브가 기본(MenuService 기반 partials.shared.local-nav)과 다르면 'local-nav' 섹션을
    오버라이드한다(예: 마이페이지의 정보수정/비밀번호 변경 탭):
        @section('local-nav')
            @include('partials.mypage.local-nav')
        @endsection
--}}
@extends('layouts.app')

@section('content')
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
