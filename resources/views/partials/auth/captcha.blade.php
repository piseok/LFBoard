@php
    $captchaProvider = app(\App\Services\SiteSettingService::class)->get('captcha_provider');
    $captchaSiteKey = app(\App\Services\SiteSettingService::class)->get('captcha_site_key');
    $captchaQuestion = $captchaProvider === 'simple_math'
        ? app(\App\Services\CaptchaService::class)->generateMathChallenge()
        : null;
    $isWidgetProvider = in_array($captchaProvider, ['hcaptcha', 'turnstile'], true);
@endphp

<div class="form-group">
    @if ($isWidgetProvider)
        <label>{{ __('보안 인증') }}</label>
        @if ($captchaProvider === 'hcaptcha')
            <div class="h-captcha" data-sitekey="{{ $captchaSiteKey }}" data-callback="__setCaptchaToken"></div>
            <script src="https://js.hcaptcha.com/1/api.js" async defer></script>
        @else
            <div class="cf-turnstile" data-sitekey="{{ $captchaSiteKey }}" data-callback="__setCaptchaToken"></div>
            <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
        @endif
    @elseif ($captchaProvider === 'recaptcha_v3')
        {{-- v3는 화면에 보이는 위젯이 없고, 제출 시점에 토큰을 받아와 채운 뒤 실제로 전송한다. --}}
        <script src="https://www.google.com/recaptcha/api.js?render={{ $captchaSiteKey }}"></script>
    @else
        <label for="captcha_token">{{ __('보안 인증') }}{{ $captchaQuestion ? " — {$captchaQuestion}" : '' }}</label>
    @endif

    <input id="captcha_token" name="captcha_token"
           type="{{ $isWidgetProvider || $captchaProvider === 'recaptcha_v3' ? 'hidden' : 'text' }}"
           @unless($isWidgetProvider || $captchaProvider === 'recaptcha_v3')
               required
               placeholder="{{ $captchaQuestion ? __('정답 입력') : __('스팸 방지 인증') }}"
               inputmode="{{ $captchaQuestion ? 'numeric' : 'text' }}"
           @endunless>
    @error('captcha_token')<p class="field-error">{{ $message }}</p>@enderror
</div>

<script>
    // hCaptcha/Turnstile 위젯의 data-callback이 인증 성공 시 토큰을 넘겨주면 hidden input에 채운다.
    function __setCaptchaToken(token) {
        var input = document.getElementById('captcha_token');
        if (input) { input.value = token; }
    }

    @if ($captchaProvider === 'recaptcha_v3')
        document.addEventListener('DOMContentLoaded', function () {
            var input = document.getElementById('captcha_token');
            var form = input ? input.closest('form') : null;
            if (!input || !form) return;

            form.addEventListener('submit', function (event) {
                if (input.dataset.captchaReady === '1') return;

                event.preventDefault();

                grecaptcha.ready(function () {
                    grecaptcha.execute(@json($captchaSiteKey), {action: 'submit'}).then(function (token) {
                        input.value = token;
                        input.dataset.captchaReady = '1';
                        form.requestSubmit ? form.requestSubmit() : form.submit();
                    });
                });
            });
        });
    @endif
</script>
