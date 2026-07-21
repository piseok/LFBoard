<?php

namespace App\Services;

use App\Services\IdentityVerification\IdentityVerificationProvider;
use App\Services\IdentityVerification\InicisProvider;
use App\Services\IdentityVerification\NiceProvider;
use RuntimeException;

class IdentityVerificationService
{
    public function __construct(private readonly SiteSettingService $siteSettings) {}

    public function isEnabled(): bool
    {
        return $this->siteSettings->get('identity_verification_enabled') === '1'
            && filled($this->siteSettings->get('identity_verification_provider'))
            && filled($this->siteSettings->get('identity_verification_merchant_id'))
            && filled($this->siteSettings->get('identity_verification_api_key'));
    }

    public function provider(): IdentityVerificationProvider
    {
        $merchantId = (string) $this->siteSettings->get('identity_verification_merchant_id');
        $apiKey = (string) $this->siteSettings->get('identity_verification_api_key');

        return match ($this->siteSettings->get('identity_verification_provider')) {
            'inicis' => new InicisProvider($merchantId, $apiKey),
            'nice' => new NiceProvider($merchantId, $apiKey),
            default => throw new RuntimeException('본인인증 공급사가 설정되지 않았습니다.'),
        };
    }
}
