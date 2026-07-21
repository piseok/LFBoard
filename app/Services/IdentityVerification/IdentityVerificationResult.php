<?php

namespace App\Services\IdentityVerification;

// 공급사 콜백을 복호화/검증한 결과. ci/di는 필수, 나머지는 공급사·인증수단에 따라 없을 수 있다.
class IdentityVerificationResult
{
    public function __construct(
        public readonly string $ci,
        public readonly string $di,
        public readonly ?string $name = null,
        public readonly ?string $phone = null,
        public readonly ?string $birthdate = null,
        public readonly ?string $gender = null,
    ) {}
}
