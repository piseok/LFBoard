<?php

namespace App\Services\IdentityVerification;

use RuntimeException;

/**
 * NICE평가정보 본인인증(NICE ID) 연동.
 *
 * 조사 결과(2026-07-03, 공식 가이드 https://auth-guide.niceid.co.kr/ 기준):
 * - 암호화 방식은 AES-256/GCM/NoPadding (과거 SEED가 아닌 AES로 전환됨).
 * - 흐름은 3단계: (1) 접근토큰 발급 → (2) 인증창 URL 요청(암호화된 요청 데이터 포함) →
 *   (3) 인증 완료 후 콜백으로 받은 web_transaction_id로 인증결과(/auth/result) 조회, 응답은
 *   계약된 항목만 내려옴(name/gender/birthdate/ci/di/mobile_no 등).
 * - 공식 가이드는 PHP 연동 시 NICE가 배포하는 전용 실행모듈(NiceIntcClient64-1.0, proc_open으로 호출)
 *   사용을 예시로 제공함 — 이 모듈은 계약 후 NICE로부터 별도로 전달받는 바이너리라, 지금 이 저장소에는
 *   포함할 수 없고 사양을 추측해 순수 PHP로 재구현하는 것은(GCM 인증 실패 시 위조 데이터를 그대로
 *   신뢰하게 되는 등) 본인인증의 보안 목적상 위험이 커 시도하지 않았다.
 *
 * 실제 계약 후 반드시 할 일:
 * 1. NICE로부터 실행모듈(NiceIntcClient64-1.0 등)과 최신 연동 매뉴얼을 전달받는다.
 * 2. 아래 buildRequest()/parseCallback() 안의 TODO 부분을 그 모듈 호출로 채운다(엔드포인트/필드명이
 *    계약 시점 매뉴얼과 다를 수 있으니 반드시 최신 문서로 재확인할 것).
 */
class NiceProvider implements IdentityVerificationProvider
{
    public function __construct(
        private readonly string $merchantId,
        private readonly string $apiKey,
    ) {}

    public function buildRequest(string $returnUrl): IdentityVerificationRequest
    {
        throw new RuntimeException(
            'NICE 본인인증은 계약 후 전달받는 전용 실행모듈(NiceIntcClient64-1.0) 연동이 필요합니다. '.
            'app/Services/IdentityVerification/NiceProvider.php의 안내 주석을 참고해 실제 모듈 호출 코드로 교체해 주세요.'
        );
    }

    public function parseCallback(array $params): IdentityVerificationResult
    {
        throw new RuntimeException(
            'NICE 본인인증은 계약 후 전달받는 전용 실행모듈(NiceIntcClient64-1.0) 연동이 필요합니다. '.
            'app/Services/IdentityVerification/NiceProvider.php의 안내 주석을 참고해 실제 모듈 호출 코드로 교체해 주세요.'
        );
    }
}
