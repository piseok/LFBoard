<?php

namespace App\Services\Ai;

// 모델이 요청한 함수 호출 1건(제공자 중립). 서버는 이것을 "실행"하지 않고 검증해 행동 카드로만 바꾼다.
final class AiToolCall
{
    /**
     * @param  array<string, mixed>  $args  모델이 준 인자(신뢰하지 않는다 - AiProposalService 가 검증·정규화)
     */
    public function __construct(
        public readonly string $name,
        public readonly array $args,
    ) {}
}
