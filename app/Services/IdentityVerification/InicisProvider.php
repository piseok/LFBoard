<?php

namespace App\Services\IdentityVerification;

use RuntimeException;

/**
 * KG이니시스 표준창 본인인증 연동.
 *
 * 조사 결과(2026-07-03): 이니시스도 특정 요청/응답 필드를 AES로 암호화해 주고받는 방식이나,
 * 공개된 자료만으로는 정확한 필드 순서/패딩/키 파생 방식을 확정할 수 없었고(공식 매뉴얼은
 * 가맹점 계약 후에만 제공됨), 이 부분을 추측으로 구현하면 검증에 실패하거나 — 더 위험하게는 —
 * 위조된 인증 결과를 그대로 통과시킬 수 있어 시도하지 않았다. NICE와 마찬가지로 계약 후 받는
 * 공식 SDK/매뉴얼로 아래 메서드를 채워야 한다.
 *
 * 참고: 조사 중 발견한 대안 — 포트원(구 아임포트, https://portone.io)은 이니시스/NICE/다날/KCP를
 * 하나의 API·SDK(PortOne.requestIdentityVerification())로 묶어 제공해, 우리가 암호화 로직을 직접
 * 구현하지 않아도 된다. 이니시스·NICE 각각과 직접 계약하는 대신 포트원 하나만 계약하는 방법도 있으니
 * 필요하면 알려달라(별도 작업으로 안내드림).
 */
class InicisProvider implements IdentityVerificationProvider
{
    public function __construct(
        private readonly string $merchantId,
        private readonly string $apiKey,
    ) {}

    public function buildRequest(string $returnUrl): IdentityVerificationRequest
    {
        throw new RuntimeException(
            'KG이니시스 본인인증은 계약 후 전달받는 공식 연동 모듈/매뉴얼이 필요합니다. '.
            'app/Services/IdentityVerification/InicisProvider.php의 안내 주석을 참고해 실제 연동 코드로 교체해 주세요.'
        );
    }

    public function parseCallback(array $params): IdentityVerificationResult
    {
        throw new RuntimeException(
            'KG이니시스 본인인증은 계약 후 전달받는 공식 연동 모듈/매뉴얼이 필요합니다. '.
            'app/Services/IdentityVerification/InicisProvider.php의 안내 주석을 참고해 실제 연동 코드로 교체해 주세요.'
        );
    }
}
