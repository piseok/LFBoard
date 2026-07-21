<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\SiteSettingService;
use App\Support\IpMatcher;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AdminIpWhitelist
{
    // 사이트 설정(보안 탭)에서 "관리자 접속 IP 제한"을 켜면, 등록된 IP/대역(CIDR)에서만
    // 관리자 패널에 접속할 수 있다. 최고관리자(super)는 실수로 스스로를 차단하는 사고를 막기 위해
    // 이 제한과 무관하게 항상 접속 가능하다. 이 미들웨어는 Filament의 authMiddleware로 등록되어
    // 로그인 이후(사용자 신원을 알 수 있는 시점)에만 적용된다 — 로그인 화면 자체는 막지 않는다.
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->level !== User::LEVEL_ADMIN) {
            return $next($request);
        }

        if ($user->admin_role === 'super' || is_null($user->admin_role)) {
            return $next($request);
        }

        try {
            $settings = app(SiteSettingService::class);
            $enabled = $settings->get('admin_ip_whitelist_enabled') === '1';

            if (! $enabled) {
                return $next($request);
            }

            $whitelist = json_decode($settings->get('admin_ip_whitelist') ?? '[]', true) ?: [];
        } catch (Throwable) {
            // 설정 조회 실패 시 관리자를 차단하지 않고 안전하게 통과시킨다.
            return $next($request);
        }

        if (empty($whitelist)) {
            return $next($request);
        }

        $ip = $request->ip();

        if (IpMatcher::matchesAny($ip, $whitelist)) {
            return $next($request);
        }

        abort(403, "허용되지 않은 IP에서의 접속입니다. (현재 접속 IP: {$ip})");
    }
}
