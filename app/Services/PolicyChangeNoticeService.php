<?php

namespace App\Services;

use App\Models\EmailTemplate;
use App\Models\Language;
use App\Models\Policy;
use App\Models\User;

// 약관/방침 저장 시 "변경 안내 메일 발송"을 체크하면 즉시(크론 없이) 발송한다.
// 관리자가 입력한 안내 메시지에 변경 전/후 내용 비교(자동 추출)를 덧붙여 보낸다.
// 다국어 D섹션 10번(policies locale 분리) 완료 후로 회원의 preferred locale이 아니라, "이 회원에게
// 실제로 보여지는 정책 버전이 바로 지금 수정된 이 행인가"를 기준으로 발송 대상을 정한다
// (Policy::findByType()의 폴백 로직을 그대로 재사용 — 예: 일본어 약관이 아직 없어 한국어로
// 폴백 중인 일본어 회원도, 한국어 약관이 바뀌면 똑같이 그 폴백 내용을 보고 있으므로 안내 대상에 포함).
// 메일 자체는 회원의 언어로 발송(inquiry_received_user/inquiry_reply와 동일한 "한국 제외 영어" 패턴,
// EmailTemplateSeeder::localizedDefinitions() 참고).
class PolicyChangeNoticeService
{
    public function __construct(private readonly EmailService $emailService) {}

    /**
     * @return array{sent: int, failed: int}
     */
    public function send(Policy $policy, ?string $originalContent, string $subject, string $adminMessage): array
    {
        $diffSummary = $this->buildDiffSummary($originalContent, $policy->content);

        // 발송 시점의 제목을 실제 메일 제목으로 반영(마케팅 메일과 동일한 패턴) — 발송 전에 미리 갱신해야 한다.
        // 지금 수정된 정책과 같은 언어의 템플릿 행에만 반영한다 — 예를 들어 en 정책을 고쳤는데
        // ko 템플릿 제목을 덮어써 버리면 안 되기 때문. 그 언어의 템플릿 행 자체가 아직 없으면(예:
        // 아직 이메일 번역이 없는 언어) 건드리지 않고 기존 제목 그대로 둔다 — 그 언어는 어차피
        // EmailTemplate::findByType()의 폴백(영어→기본 언어)으로 다른 행의 제목을 그대로 받는다.
        if (EmailTemplate::where('type', 'policy_change_notice')->where('locale', $policy->locale)->exists()) {
            EmailTemplate::where('type', 'policy_change_notice')->where('locale', $policy->locale)->update(['subject' => $subject]);
        }

        $recipients = User::query()
            ->where('level', '!=', User::LEVEL_ADMIN)
            ->where('is_active', true)
            ->whereNotNull('email')
            ->get()
            ->filter(fn (User $user): bool => Policy::findByType($policy->type, $user->locale)?->is($policy) ?? false);

        $sentCount = 0;
        $failedCount = 0;

        foreach ($recipients as $user) {
            $sent = $this->emailService->send('policy_change_notice', $user->email, [
                'user_name' => $user->name,
                'policy_title' => $policy->title,
                'admin_message' => $adminMessage,
                'diff_summary' => $diffSummary,
                'policy_url' => $this->policyUrl($policy, $user->locale),
            ], $user->locale);

            $sent ? $sentCount++ : $failedCount++;
        }

        return ['sent' => $sentCount, 'failed' => $failedCount];
    }

    // 리치에디터로 저장되는 HTML을 그대로 비교하면 표/서식 태그 때문에 diff가 지저분해지므로,
    // 블록 태그를 줄바꿈으로 치환한 뒤 strip_tags로 순수 텍스트만 뽑아 줄 단위로 비교한다
    // (무거운 diff 라이브러리 없이 배열 비교 수준으로 충분 — 변경 전/후 각각 달라진 줄만 추출).
    private function buildDiffSummary(?string $before, ?string $after): string
    {
        $beforeLines = $this->toLines($before);
        $afterLines = $this->toLines($after);

        $removed = array_diff($beforeLines, $afterLines);
        $added = array_diff($afterLines, $beforeLines);

        if (empty($removed) && empty($added)) {
            return '<p>(서식 등 세부 변경 외에 본문 내용 변경은 감지되지 않았습니다.)</p>';
        }

        $html = '';

        if (! empty($removed)) {
            $html .= '<p><strong>변경 전 (삭제/수정된 부분):</strong></p><ul>';
            foreach ($removed as $line) {
                $html .= '<li>'.e($line).'</li>';
            }
            $html .= '</ul>';
        }

        if (! empty($added)) {
            $html .= '<p><strong>변경 후 (추가/수정된 부분):</strong></p><ul>';
            foreach ($added as $line) {
                $html .= '<li>'.e($line).'</li>';
            }
            $html .= '</ul>';
        }

        return $html;
    }

    /**
     * @return array<int, string>
     */
    private function toLines(?string $html): array
    {
        $withBreaks = preg_replace('/<\/(p|div|li|h[1-6]|tr)>|<br\s*\/?>/i', "\n", (string) $html);
        $text = strip_tags((string) $withBreaks);

        $lines = array_map('trim', explode("\n", $text));

        return array_values(array_filter($lines, fn (string $line): bool => $line !== ''));
    }

    // 이메일에 넣을 URL이라 요청 컨텍스트(app()->getLocale())와 무관하게, 수신자 각자의 언어로
    // 정확한 링크를 만들어야 한다 — front_route()는 현재 요청 로케일 기준이라 여기선 쓸 수 없고,
    // Language::routeNamePrefix($locale)로 직접 그 언어의 라우트 이름을 지정한다.
    private function policyUrl(Policy $policy, string $locale): string
    {
        $prefix = Language::routeNamePrefix($locale);

        return match ($policy->type) {
            'terms' => route($prefix.'policy.terms'),
            'privacy' => route($prefix.'policy.privacy'),
            'marketing' => route($prefix.'policy.marketing'),
            default => url('/'),
        };
    }
}
