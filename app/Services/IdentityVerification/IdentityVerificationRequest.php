<?php

namespace App\Services\IdentityVerification;

// 공급사 인증창(팝업/새창)에 자동 제출(auto-submit)할 폼 정보.
class IdentityVerificationRequest
{
    /**
     * @param  array<string, string>  $formParams
     */
    public function __construct(
        public readonly string $actionUrl,
        public readonly array $formParams,
    ) {}
}
