<?php

namespace App\Services\Ai;

use RuntimeException;

// 제공자의 기존 chat() 을 chatWithTools() 로 위임한다(시스템 지시문·도구 없이) - 기존 호출부와 시험 보존.
trait DelegatesPlainChat
{
    public function chat(array $history): string
    {
        $result = $this->chatWithTools('', $history, []);

        if ($result->text === '') {
            throw new RuntimeException($this->label().' 응답을 해석할 수 없습니다.');
        }

        return $result->text;
    }
}
