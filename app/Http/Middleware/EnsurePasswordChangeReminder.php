<?php

namespace App\Http\Middleware;

use App\Models\Language;
use App\Services\SiteSettingService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// 법적 의무는 아니지만(2023년 개인정보보호위원회가 비밀번호 정기 변경 의무를 공식 폐지) 원하는
// 고객사를 위한 선택 기능. 사이트 설정에서 켠 경우에만, 지정한 개월수를 넘긴 회원(일반회원/
// 정회원 등 관리자가 아닌 모든 가입 등급)을 로그인 후 변경 안내 화면으로 보낸다.
// 약관 재동의(EnsureRequiredPolicyConsent)와 달리
// 이건 강제 차단이 아니라 "나중에 하기"로 넘길 수 있고, 한 번 넘기면 그 로그인 세션 동안은
// 다시 묻지 않는다(세션에만 남기고 DB에 기록하지 않음 — 재로그인하면 다시 확인).
class EnsurePasswordChangeReminder
{
    private const EXCLUDED_ROUTE_NAMES = [
        'password-reminder.show', 'password-reminder.dismiss',
        'mypage.password.edit', 'mypage.password.update', 'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isMember()) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();
        $prefix = Language::routeNamePrefix();

        foreach (self::EXCLUDED_ROUTE_NAMES as $excluded) {
            if ($routeName === $excluded || $routeName === $prefix.$excluded) {
                return $next($request);
            }
        }

        if (session('password_reminder_dismissed')) {
            return $next($request);
        }

        $settings = app(SiteSettingService::class);

        if ($settings->get('password_change_reminder_enabled') !== '1') {
            return $next($request);
        }

        $changedAt = $user->password_changed_at ?? $user->created_at;
        $months = max(1, (int) $settings->get('password_change_reminder_months', '6'));

        if (! $changedAt || $changedAt->diffInMonths(now()) < $months) {
            return $next($request);
        }

        return redirect(front_route('password-reminder.show'));
    }
}
