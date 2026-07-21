<div class="cookie-consent" role="dialog" aria-live="polite" aria-label="{{ __('쿠키 사용 동의') }}">
    <p class="cookie-consent-message">{{ $message }}</p>
    <div class="cookie-consent-actions">
        <button type="button" class="btn btn-sm cookie-consent-reject" data-consent="rejected">{{ __('거부') }}</button>
        <button type="button" class="btn btn-primary btn-sm cookie-consent-accept" data-consent="accepted">{{ __('동의') }}</button>
    </div>
</div>
