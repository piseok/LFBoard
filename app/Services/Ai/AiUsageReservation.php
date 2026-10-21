<?php

namespace App\Services\Ai;

use App\Models\AiChatUsage;
use Illuminate\Contracts\Cache\Lock;

// 제공자 호출 직전에 잡은 "사용 1건 + 관리자별 동시 1건 잠금". 호출이 끝나면(성공이든 실패든) release() 로
// 잠금을 푼다. 사용 기록 자체는 되돌리지 않는다 - 실패한 호출도 제공자 쪽에서 과금될 수 있어 보수적으로 센다.
final class AiUsageReservation
{
    private bool $released = false;

    public function __construct(
        public readonly AiChatUsage $usage,
        private readonly Lock $lock,
    ) {}

    public function release(): void
    {
        if ($this->released) {
            return;
        }

        $this->released = true;
        $this->lock->release();
    }
}
