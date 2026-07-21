@extends('layouts.app')

{{-- 일본어 홈페이지 전용 오버라이드 예시 — resources/views/home/index.blade.php와 다른 레이아웃임을
     보여주기 위한 테스트용 뷰(DetectLocale 미들웨어의 뷰 오버라이드 경로 등록 검증용).
     실제 디자인은 추후 필요에 맞게 교체하면 됨. --}}
@section('content')
    <div style="background:#1a1a2e;color:#fff;padding:40px;border-radius:12px;margin-bottom:24px;">
        <h1 class="page-title" style="color:#fff;">{{ app(\App\Services\SiteSettingService::class)->getLocalized('site_name', default: config('app.name')) }} (日本語レイアウト)</h1>
        <p>{{ app(\App\Services\SiteSettingService::class)->get('site_description') }}</p>
    </div>

    @include('partials.home.site-search')

    @if ($banners->isNotEmpty())
        <section style="margin-bottom: 32px;">
            {{-- link_url이 없는 배너는 클릭 경유(banner.click) 링크도 만들지 않는다(홈 이동 폴백 방지). --}}
            <x-slider :items="$banners->map(fn ($banner) => ['content_type' => $banner->content_type, 'html_content' => $banner->html_content, 'image_path' => $banner->image_path, 'link_url' => $banner->link_url ? front_route('banner.click', $banner) : null, 'link_target' => $banner->link_target, 'alt_text' => $banner->alt_text ?: $banner->title])" aria-label="メインバナー" />
        </section>
    @endif
@endsection
