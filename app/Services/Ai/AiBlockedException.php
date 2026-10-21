<?php

namespace App\Services\Ai;

use RuntimeException;

// 제공자가 "정책상" 응답을 만들지 않았다는 신호(저작권 유사·안전·금지 콘텐츠 등). 실패(네트워크·형식 오류)와 달리 관리자가 요청 표현을
// 바꾸면 해결되는 일이라 이유별 안내 문구를 만들어 그대로 보여 준다. 사유 코드(예: IMAGE_RECITATION)만 노출하고, 제공자의 원문 응답·
// finishMessage·키는 절대 노출하지 않는다(코드는 [A-Z_] 만 통과시킨다). 차단된 호출은 만들어진 결과가 없으므로 일일 한도를 소모하지 않는다
// (AiChatService 가 사용 기록을 되돌린다).
class AiBlockedException extends RuntimeException
{
    public function __construct(public readonly string $reasonCode, public readonly string $kind, string $message)
    {
        parent::__construct($message);
    }

    public static function forImage(string $rawCode): self
    {
        $code = self::cleanCode($rawCode);

        return new self($code, 'image', match (true) {
            str_contains($code, 'RECITATION') => "기존 저작물과 너무 비슷하다고 판단되어 이미지가 정책상 생성되지 않았습니다({$code}). 표현을 바꿔 다시 요청해 주세요.",
            str_contains($code, 'SAFETY'), str_contains($code, 'PROHIBITED'), $code === 'BLOCKLIST', $code === 'SPII' => "안전 정책에 따라 이미지가 정책상 생성되지 않았습니다({$code}). 표현을 바꿔 다시 요청해 주세요.",
            default => "이미지가 정책상 생성되지 않았습니다({$code}). 표현을 바꿔 다시 요청해 주세요.",
        });
    }

    public static function forChat(string $rawCode): self
    {
        $code = self::cleanCode($rawCode);

        return new self($code, 'chat', match (true) {
            str_contains($code, 'RECITATION') => "기존 저작물과 너무 비슷해 답변이 정책상 생성되지 않았습니다({$code}). 질문 표현을 바꿔 다시 요청해 주세요.",
            default => "답변이 정책상 생성되지 않았습니다({$code}). 질문 표현을 바꿔 다시 요청해 주세요.",
        });
    }

    // 사유 코드는 [A-Z_] 만 허용한다(제공자가 보낸 임의 문자열이 화면·로그로 그대로 나가지 않게).
    private static function cleanCode(string $rawCode): string
    {
        $code = strtoupper(trim($rawCode));

        return preg_match('/^[A-Z][A-Z_]{1,39}$/', $code) === 1 ? $code : 'UNKNOWN';
    }
}
