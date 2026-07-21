<?php

namespace App\Services\IdentityVerification;

interface IdentityVerificationProvider
{
    // 인증창을 띄우기 위해 필요한 요청 파라미터를 만든다.
    public function buildRequest(string $returnUrl): IdentityVerificationRequest;

    // 콜백으로 돌아온 원본 파라미터를 검증/복호화한다. 실패 시 예외를 던진다.
    public function parseCallback(array $params): IdentityVerificationResult;
}
