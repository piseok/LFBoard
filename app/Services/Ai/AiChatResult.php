<?php

namespace App\Services\Ai;

// 제공자 한 번 호출의 결과 - 응답의 모든 파트에서 모은 텍스트와 함수 호출 목록(제공자 중립).
final class AiChatResult
{
    /**
     * @param  array<int, AiToolCall>  $toolCalls
     */
    public function __construct(
        public readonly string $text,
        public readonly array $toolCalls = [],
    ) {}
}
