<?php

namespace App\Services;

use App\Models\AiChatUsage;
use App\Models\User;
use App\Services\Ai\AiBusyException;
use App\Services\Ai\AiLimitExceededException;
use App\Services\Ai\AiUsageReservation;
use Illuminate\Support\Facades\Cache;

// AI 비서 일일 한도(예전에는 한도가 아예 없었다) - 비용 폭주를 막는 장치다.
//
//  - 단위: 제공자 호출 1회(질문 chat / 이미지 image 를 따로 센다). 대화를 지워도 줄지 않는다(ai_chat_usage 는 대화와 독립).
//  - 범위: 관리자별 + 사이트 전체. 둘 중 하나라도 넘으면 막는다. "오늘"은 앱 시간대(config app.timezone) 자정 기준.
//  - 경쟁 방어: 제공자를 부르기 "직전에" 먼저 한 줄을 적고 → 오늘 건수를 센 뒤 → 한도를 넘었으면 그 줄을 지우고 거절한다.
//    관리자별로 동시에 1건만 처리한다(Cache::lock). 사이트 전체 합계는 동시 요청에서 더 엄격한 쪽으로만 틀린다.
//  - 0 = 해당 종류를 쓰지 않음(차단). 무제한은 없다(비용 안전). 값이 비었거나 숫자가 아니면 기본값을 쓴다.
//  - 소모 기준(사용자 확정 2026-10-21): 제공자가 "정책상 결과를 만들지 않은" 차단 응답 - 명시 허용 목록(GeminiProvider::POLICY_REASONS:
//    IMAGE_RECITATION·RECITATION·IMAGE_SAFETY·SAFETY·PROHIBITED_CONTENT·IMAGE_PROHIBITED_CONTENT·BLOCKLIST·SPII, OpenAI 는 content_filter·
//    content_policy_violation·moderation_blocked)의 사유일 때만 한도를 소모하지 않는다 - 선기록한 줄을 AiUsageReservation::refund() 로
//    되돌린다(호출자: AiChatService). IMAGE_OTHER·OTHER·NO_IMAGE 같은 일반 코드와 5xx·타임아웃·연결 오류·형식 오류는 제공자 쪽에서
//    비용이 들었을 수 있어 소모한다(되돌리지 않음).
//  - 환불 횟수 상한은 두지 않는다(사용자 결정 2026-10-21: 내부용 도구라 인당 하루 한도만 있으면 충분). 정책 거절만 계속 반복해도 하루 한도에는
//    안 걸리고 관리자별 동시 1건 잠금만이 속도 제한이 된다는 점은 알고 받아들인 것이다(리뷰 M3 의 환불 횟수 상한 제안은 채택하지 않음).
//  - 제공자 크레딧 잔액은 조회 수단이 없어 보여 주지 않는다 - 화면에는 "우리 사이트의 일일 한도 기준"이라고 쓴다.
class AiUsageService
{
    public const KIND_CHAT = AiChatUsage::KIND_CHAT;

    public const KIND_IMAGE = AiChatUsage::KIND_IMAGE;

    // 보수적인 기본값(사용자 결정 필요 항목 - 사이트 설정 > AI 연동에서 바꾼다).
    public const DEFAULT_LIMITS = [
        self::KIND_CHAT => ['admin' => 30, 'site' => 150],
        self::KIND_IMAGE => ['admin' => 5, 'site' => 20],
    ];

    public const SETTING_KEYS = [
        self::KIND_CHAT => ['admin' => 'ai_chat_daily_limit_per_admin', 'site' => 'ai_chat_daily_limit'],
        self::KIND_IMAGE => ['admin' => 'ai_image_daily_limit_per_admin', 'site' => 'ai_image_daily_limit'],
    ];

    // 관리자 1명이 동시에 제공자를 부르는 것을 막는 잠금 유효 시간(초) - 요청이 죽어도 이 시간 뒤엔 풀린다.
    private const LOCK_SECONDS = 120;

    // 사용 기록을 남겨 두는 최소 일수(오늘 건수만 필요하지만 감독 화면에서 며칠치를 볼 수 있게).
    public const KEEP_DAYS = 35;

    public function __construct(private readonly SiteSettingService $siteSettings) {}

    public function limit(string $kind, string $scope): int
    {
        $raw = $this->siteSettings->get(self::SETTING_KEYS[$kind][$scope]);

        if ($raw === null || trim($raw) === '' || ! preg_match('/^\d+$/', trim($raw))) {
            return self::DEFAULT_LIMITS[$kind][$scope];
        }

        return min((int) trim($raw), 100000);
    }

    public function used(string $kind, ?User $admin = null): int
    {
        return AiChatUsage::query()
            ->where('kind', $kind)
            ->when($admin, fn ($q) => $q->where('user_id', $admin->id))
            ->where('created_at', '>=', now()->startOfDay())
            ->count();
    }

    /**
     * 화면 표시용 오늘 현황(API 호출 없이 ai_chat_usage 로만 계산).
     *
     * @return array{kind: string, admin_used: int, admin_limit: int, site_used: int, site_limit: int, remaining: int, blocked_by: ?string}
     */
    public function status(User $admin, string $kind): array
    {
        $adminUsed = $this->used($kind, $admin);
        $siteUsed = $this->used($kind);
        $adminLimit = $this->limit($kind, 'admin');
        $siteLimit = $this->limit($kind, 'site');

        $adminRemaining = max(0, $adminLimit - $adminUsed);
        $siteRemaining = max(0, $siteLimit - $siteUsed);

        return [
            'kind' => $kind,
            'admin_used' => $adminUsed,
            'admin_limit' => $adminLimit,
            'site_used' => $siteUsed,
            'site_limit' => $siteLimit,
            'remaining' => min($adminRemaining, $siteRemaining),
            // 0 이 되는 원인 - 안내 문구를 달리 쓰기 위해(내 한도 vs 사이트 전체 한도).
            'blocked_by' => $adminRemaining === 0 ? 'admin' : ($siteRemaining === 0 ? 'site' : null),
        ];
    }

    /**
     * 제공자 호출 직전에 호출한다. 한도를 넘으면 예외(비용 발생 없음). 성공하면 반드시 reservation->release() 를 부른다.
     *
     * @throws AiLimitExceededException
     * @throws AiBusyException
     */
    public function reserve(User $admin, string $providerKey, string $kind): AiUsageReservation
    {
        $lock = Cache::lock("ai-inflight:{$kind}:{$admin->id}", self::LOCK_SECONDS);

        if (! $lock->get()) {
            throw new AiBusyException(__('이전 AI 요청을 처리하고 있습니다. 잠시 후 다시 시도해주세요.'));
        }

        try {
            // 먼저 적고 → 센 뒤 → 넘었으면 되돌린다.
            $usage = AiChatUsage::create([
                'user_id' => $admin->id,
                'provider' => $providerKey,
                'kind' => $kind,
                'created_at' => now(),
            ]);

            $status = $this->status($admin, $kind);
            $label = $kind === self::KIND_IMAGE ? '이미지 생성' : '질문';

            if ($status['admin_used'] > $status['admin_limit']) {
                $usage->delete();

                throw new AiLimitExceededException(
                    __("오늘 내가 쓸 수 있는 AI {$label} 한도(:limit회)를 모두 썼습니다. 내일 0시에 초기화됩니다.", ['limit' => $status['admin_limit']]),
                    $kind, 'admin', $status['admin_limit'],
                );
            }

            if ($status['site_used'] > $status['site_limit']) {
                $usage->delete();

                throw new AiLimitExceededException(
                    __("오늘 사이트 전체 AI {$label} 한도(:limit회)를 모두 썼습니다. 내일 0시에 초기화됩니다.", ['limit' => $status['site_limit']]),
                    $kind, 'site', $status['site_limit'],
                );
            }
        } catch (\Throwable $e) {
            $lock->release();

            throw $e;
        }

        return new AiUsageReservation($usage, $lock);
    }

    /**
     * 사이트 설정·감독 화면용: 오늘 관리자별 사용량 표.
     *
     * @return array{rows: array<int, array{name: string, chat: int, image: int}>, chat_total: int, image_total: int}
     */
    public function todaySummary(): array
    {
        $start = now()->startOfDay();

        $rows = AiChatUsage::query()
            ->selectRaw('user_id, kind, count(*) as total')
            ->where('created_at', '>=', $start)
            ->groupBy('user_id', 'kind')
            ->get();

        $names = User::query()->whereIn('id', $rows->pluck('user_id')->filter()->unique())->pluck('name', 'id');

        $byUser = [];
        foreach ($rows as $row) {
            $key = $row->user_id ?? 0;
            $byUser[$key] ??= ['name' => $row->user_id ? ($names[$row->user_id] ?? '(알 수 없음)') : '(삭제된 계정)', 'chat' => 0, 'image' => 0];
            $byUser[$key][$row->kind] = (int) $row->total;
        }

        $list = array_values($byUser);
        usort($list, fn (array $a, array $b) => ($b['chat'] + $b['image']) <=> ($a['chat'] + $a['image']));

        return [
            'rows' => $list,
            'chat_total' => array_sum(array_column($list, 'chat')),
            'image_total' => array_sum(array_column($list, 'image')),
        ];
    }

    // 오래된 사용 기록 정리(AiChatService::pruneExpired 가 부른다). 오늘 한도 계산에는 영향이 없다.
    public function pruneOld(int $keepDays): void
    {
        AiChatUsage::query()->where('created_at', '<', now()->subDays(max($keepDays, self::KEEP_DAYS)))->delete();
    }
}
