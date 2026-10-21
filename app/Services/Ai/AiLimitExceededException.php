<?php

namespace App\Services\Ai;

// 오늘 한도를 넘어 제공자 호출을 시작하지 않았다는 신호(비용이 발생하지 않음). 화면은 이 예외의 메시지를 그대로 보여 준다.
class AiLimitExceededException extends AiUserFacingException
{
    public function __construct(
        string $message,
        public readonly string $kind,
        public readonly string $scope,
        public readonly int $limit,
    ) {
        parent::__construct($message);
    }
}
