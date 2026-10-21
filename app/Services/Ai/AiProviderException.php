<?php

namespace App\Services\Ai;

use RuntimeException;

// 제공자 호출이 실패했다는 신호(HTTP 오류·해석 불가 응답). 운영에서 "왜 갑자기 안 되는지"(퇴역 모델 404·잘못된 키 401/403·한도 429)를
// 알 수 있도록 상태 코드와 사유 코드만 담는다 - 제공자의 원문 응답·키·finishMessage 는 절대 담지 않는다(코드는 영문/숫자/_.- 60자 이내만 통과).
// 메시지는 서버가 쓴 고정 문구이며 관리자에게 보여 주지 않는다(위젯은 일반 문구를 쓴다).
class AiProviderException extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $status = null, public readonly ?string $reason = null)
    {
        parent::__construct($message);
    }

    // 제공자가 보낸 값을 로그에 남겨도 안전한 사유 코드로 바꾼다(아니면 null).
    public static function cleanReason(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return preg_match('/^[A-Za-z0-9_.\-]{1,60}$/', $value) === 1 ? $value : null;
    }
}
