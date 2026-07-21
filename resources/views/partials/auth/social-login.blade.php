@php
    $socialSettings = app(\App\Services\SiteSettingService::class);
    $socialProviders = [
        'google' => __('구글로 계속하기'),
        'kakao' => __('카카오로 계속하기'),
        'naver' => __('네이버로 계속하기'),
    ];
    $configuredSocialProviders = collect($socialProviders)
        ->filter(fn ($label, $provider) => $socialSettings->get("social_{$provider}_enabled") === '1'
            && filled($socialSettings->get("social_{$provider}_client_id")));
@endphp

@if ($configuredSocialProviders->isNotEmpty())
    <div class="social-login">
        @foreach ($configuredSocialProviders as $provider => $label)
            <a href="{{ route('social.redirect', $provider) }}?locale={{ app()->getLocale() }}" class="btn btn-social-{{ $provider }}">{{ $label }}</a>
        @endforeach
    </div>
@endif
