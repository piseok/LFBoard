<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;

// 크론 없는 공유호스팅 환경이라 관리자가 접속할 때마다(하루 한 번, 캐시로 중복 실행 방지)
// 휴면 전환/강제탈퇴 대상과 예고 발송 대상을 함께 확인한다(RecordAdminAccess의 로그 정리와 동일한 패턴).
// 휴면계정 정책 자체가 한국 서비스 관행(과거 정보통신망법 29조 기반)이라 외국어(비한국어) 회원에게는
// 적용하지 않는다 — 선호 언어가 한국어인 회원만 대상으로 한다(사용자 지시).
class DormantAccountService
{
    // 휴일 등으로 정확한 예고일에 관리자가 접속하지 못해도 놓치지 않도록 앞뒤 7일까지 허용한다.
    private const NOTICE_TOLERANCE_DAYS = 7;

    public function __construct(
        private readonly SiteSettingService $siteSettings,
        private readonly EmailService $emailService,
        private readonly SmsService $smsService,
        private readonly KakaoAlimTalkService $kakaoService,
    ) {}

    public function processDueAccounts(): void
    {
        if ($this->siteSettings->get('dormant_processing_enabled') !== '1') {
            return;
        }

        $dormantMonths = (int) $this->siteSettings->get('dormant_conversion_months', '12');
        $withdrawalMonths = (int) $this->siteSettings->get('forced_withdrawal_months', '24');
        $dormantNoticeDays = (int) $this->siteSettings->get('dormant_notice_days', '14');
        $withdrawalNoticeDays = (int) $this->siteSettings->get('withdrawal_notice_days', '14');

        User::query()
            ->where('level', '!=', User::LEVEL_ADMIN)
            ->where('locale', 'ko')
            ->whereNull('dormant_at')
            ->whereNotNull('last_login_at')
            ->chunkById(200, function ($users) use ($dormantMonths, $dormantNoticeDays): void {
                foreach ($users as $user) {
                    $this->handleActiveUser($user, $dormantMonths, $dormantNoticeDays);
                }
            });

        User::query()
            ->where('level', '!=', User::LEVEL_ADMIN)
            ->where('locale', 'ko')
            ->whereNotNull('dormant_at')
            ->chunkById(200, function ($users) use ($withdrawalMonths, $withdrawalNoticeDays): void {
                foreach ($users as $user) {
                    $this->handleDormantUser($user, $withdrawalMonths, $withdrawalNoticeDays);
                }
            });
    }

    private function handleActiveUser(User $user, int $dormantMonths, int $noticeDays): void
    {
        $dormantDueAt = $user->last_login_at->copy()->addMonths($dormantMonths);

        if (now()->greaterThanOrEqualTo($dormantDueAt)) {
            $user->forceFill(['dormant_at' => now()])->save();

            return;
        }

        $noticeDueAt = $dormantDueAt->copy()->subDays($noticeDays);

        if ($this->isWithinToleranceWindow($noticeDueAt) && $this->noticeNotYetSentForCycle($user->dormant_notice_sent_at, $dormantDueAt)) {
            $this->sendNotice($user, 'dormant_notice', $dormantMonths, $noticeDays);
            $user->forceFill(['dormant_notice_sent_at' => now()])->save();
        }
    }

    private function handleDormantUser(User $user, int $withdrawalMonths, int $noticeDays): void
    {
        $withdrawalDueAt = $user->last_login_at->copy()->addMonths($withdrawalMonths);

        if (now()->greaterThanOrEqualTo($withdrawalDueAt)) {
            $user->anonymizeAndWithdraw();

            return;
        }

        $noticeDueAt = $withdrawalDueAt->copy()->subDays($noticeDays);

        if ($this->isWithinToleranceWindow($noticeDueAt) && $this->noticeNotYetSentForCycle($user->withdrawal_notice_sent_at, $withdrawalDueAt)) {
            $this->sendNotice($user, 'withdrawal_notice', $withdrawalMonths, $noticeDays);
            $user->forceFill(['withdrawal_notice_sent_at' => now()])->save();
        }
    }

    private function isWithinToleranceWindow(Carbon $noticeDueAt): bool
    {
        return now()->between(
            $noticeDueAt->copy()->subDays(self::NOTICE_TOLERANCE_DAYS),
            $noticeDueAt->copy()->addDays(self::NOTICE_TOLERANCE_DAYS),
        );
    }

    // 같은 전환 예정일(dueAt) 기준으로 이미 발송했다면 재발송하지 않는다.
    // (재로그인 없이 계속 미접속 상태가 이어지는 한 dueAt은 바뀌지 않으므로, 발송 이력 하나로 충분히 판별 가능)
    private function noticeNotYetSentForCycle(?Carbon $sentAt, Carbon $dueAt): bool
    {
        return $sentAt === null || $sentAt->lessThan($dueAt->copy()->subMonths(1));
    }

    private function sendNotice(User $user, string $type, int $totalMonths, int $noticeDays): void
    {
        $variables = [
            'user_name' => $user->name,
            'inactive_months' => (string) $totalMonths,
            'notice_days' => (string) $noticeDays,
            'login_url' => route('login'),
        ];

        if ($this->siteSettings->get("email_{$type}_enabled") === '1') {
            $this->emailService->send($type, $user->email, $variables);
        }

        if (filled($user->phone)) {
            $message = "[안내] {$totalMonths}개월 이상 미접속으로 회원님의 계정이 곧 {$noticeDays}일 후 ".
                ($type === 'dormant_notice' ? '휴면 전환' : '탈퇴 처리').'될 예정입니다. 계속 이용하시려면 로그인해 주세요.';

            if ($this->siteSettings->get('dormant_notice_sms_enabled') === '1') {
                $this->smsService->send($user->phone, $message);
            }

            if ($this->siteSettings->get('dormant_notice_kakao_enabled') === '1') {
                $this->kakaoService->send($user->phone, $type, $message, $message);
            }
        }
    }
}
