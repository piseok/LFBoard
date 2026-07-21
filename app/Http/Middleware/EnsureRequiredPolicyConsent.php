<?php

namespace App\Http\Middleware;

use App\Models\Language;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// 필수 약관(이용약관/개인정보처리방침) 버전이 바뀌었는데 회원의 최신 동의 기록이 그 버전과 다르면
// 재동의 화면으로 강제 이동시킨다(재동의 전까지 이용 차단 — 사용자 확정 방식). 약관 자체를 읽거나
// 재동의를 제출하는 데 필요한 라우트는 무한 리다이렉트를 막기 위해 예외로 둔다.
class EnsureRequiredPolicyConsent
{
    private const EXCLUDED_ROUTE_NAMES = [
        'policy.terms', 'policy.privacy', 'policy.marketing', 'policy.change-notice',
        'policy-consent.show', 'policy-consent.store',
        'logout',
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

        if (empty($user->outdatedRequiredPolicyTypes())) {
            return $next($request);
        }

        return redirect(front_route('policy-consent.show'));
    }
}
