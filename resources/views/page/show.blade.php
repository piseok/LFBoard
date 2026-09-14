@extends('layouts.subpage')

@section('subcontent')
    <article>
        <x-sub-header :title="$page->title" />

        {{-- isset() 방어: FrontController::page()는 항상 $banners를 넘기지만, 배포 과정에서
             컨트롤러 반영 없이 이 뷰 파일만 먼저 올라가는 등 드리프트가 생겨도(2026-09-14 실제
             운영 서버 "Undefined variable $banners" 재현) 이 섹션만 조용히 생략되게 한다. --}}
        @if (isset($banners) && $banners->isNotEmpty())
            <x-slider :items="$banners" aria-label="{{ __('배너') }}" />
        @endif

        <div class="post-content">{!! $content !!}</div>
    </article>
@endsection
