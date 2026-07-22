@php
    $siteSettings = app(\App\Services\SiteSettingService::class);
    $footerTermsPolicy = \App\Models\Policy::findByType('terms', app()->getLocale());
    $footerPrivacyPolicy = \App\Models\Policy::findByType('privacy', app()->getLocale());
    $footerEmailNoticePolicy = \App\Models\Policy::findByType('email_notice', app()->getLocale());
    $siteName = $siteSettings->getLocalized('site_name', default: config('app.name'));
    // 푸터 로고를 따로 등록하지 않았으면 헤더 로고를 그대로 재사용한다.
    $siteLogo = $siteSettings->get('footer_logo') ?: $siteSettings->get('site_logo');
    $familySites = json_decode($siteSettings->get('family_sites', '[]'), true) ?: [];

    $footerBanners = \App\Models\Banner::query()
        ->where('group_key', 'footer')
        ->where('locale', app()->getLocale())
        ->where('is_active', true)
        ->where(function ($q) {
            $q->whereNull('started_at')->orWhere('started_at', '<=', now());
        })
        ->where(function ($q) {
            $q->whereNull('ended_at')->orWhere('ended_at', '>=', now());
        })
        ->orderBy('sort_order')
        ->get();
@endphp

@if ($siteSettings->get('show_footer_inquiry', '1') === '1')
    @include('partials.layout.footer-inquiry')
@endif

<footer class="footer">
    <div class="container">
        @if ($footerBanners->isNotEmpty())
            <div class="footer-top">
                {{-- link_url이 없는 배너는 클릭 경유(banner.click) 링크도 만들지 않는다(홈 이동 폴백 방지). --}}
                <x-slider :items="$footerBanners->map(fn ($banner) => ['content_type' => $banner->content_type, 'html_content' => $banner->html_content, 'image_path' => $banner->image_path, 'link_url' => $banner->link_url ? route('banner.click', $banner) : null, 'link_target' => $banner->link_target, 'alt_text' => $banner->alt_text ?: $banner->title])" :arrows="false" pagination="none" aria-label="{{ __('푸터 배너') }}" />
            </div>
        @endif

        <div class="footer-bottom">
            <div class="footer-logo">
                @if ($siteLogo)
                    <img src="{{ url($siteLogo) }}" alt="{{ $siteName }}">
                @else
                    {{ $siteName }}
                @endif
            </div>

            <div class="footer-info">
                <nav aria-label="{{ __('정책 메뉴') }}" class="footer-link">
                    @if ($footerPrivacyPolicy)
                        <a href="{{ front_route('policy.privacy') }}">{{ $footerPrivacyPolicy->title }}</a>
                    @endif
                    @if ($footerTermsPolicy)
                        <a href="{{ front_route('policy.terms') }}">{{ $footerTermsPolicy->title }}</a>
                    @endif
                    @if ($footerEmailNoticePolicy)
                        <a href="{{ front_route('policy.email-notice') }}">{{ $footerEmailNoticePolicy->title }}</a>
                    @endif
                    <a href="{{ front_route('sitemap') }}">{{ __('사이트맵') }}</a>
                </nav>

                @if ($address = $siteSettings->get('footer_address'))
                    <p>{{ $address }}</p>
                @endif
                @if ($phone = $siteSettings->get('footer_phone'))
                    <p>{{ $phone }}</p>
                @endif

                <p>{{ $siteSettings->getLocalized('footer_copyright') ?: '© '.date('Y').' '.$siteName }}</p>
            </div>

            <div class="footer-util">
                @if (! empty($familySites))
                    <div class="family-site">
                        <button type="button" class="family-btn">
                            Family Site
                        </button>
                        <div class="family-list">
                            @foreach ($familySites as $site)
                                <a href="{{ $site['url'] ?? '#' }}" target="_blank" rel="noopener">{{ $site['name'] ?? '' }}</a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</footer>
