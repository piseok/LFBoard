<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\SiteSettingService;
use App\Support\IpMatcher;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SiteIpBlocklist
{
    // 사이트 설정(보안 탭)에서 "사이트 접속 차단"을 켜면, 등록된 IP/대역(CIDR)에서의 접속을 전면 차단한다.
    // 특정 국가를 막고 싶은 경우, 해당 국가의 공개된 IP 대역을 직접 조사해 CIDR로 등록하면 된다
    // (이 프로젝트는 국가 자동 판별(GeoIP)을 내장하지 않는다 — MaxMind 등 GeoIP DB는 라이선스 키가 필요하거나
    // 주기적 갱신이 필요한데, 이 프로젝트는 크론이 없는 공유호스팅 제약과 "키 불필요" 원칙을 지키기 위해
    // 자동 갱신 없는 수동 CIDR 등록 방식만 지원한다).
    // 관리자로 로그인한 사용자는 프론트에서 스스로를 차단하는 사고를 막기 위해 이 제한을 적용받지 않는다
    // (Filament 관리자 패널 자체는 이 미들웨어가 등록된 web 그룹을 타지 않으므로 애초에 영향받지 않는다).
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->level === User::LEVEL_ADMIN) {
            return $next($request);
        }

        try {
            $settings = app(SiteSettingService::class);
            $enabled = $settings->get('site_ip_blocklist_enabled') === '1';

            if (! $enabled) {
                return $next($request);
            }

            $blocklist = json_decode($settings->get('site_ip_blocklist') ?? '[]', true) ?: [];
        } catch (Throwable) {
            // 설정 조회 실패 시 방문자를 차단하지 않고 안전하게 통과시킨다.
            return $next($request);
        }

        if (empty($blocklist)) {
            return $next($request);
        }

        if (IpMatcher::matchesAny($request->ip(), $blocklist)) {
            abort(403, __('접속이 제한된 IP입니다.'));
        }

        return $next($request);
    }
}
