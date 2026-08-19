<?php

namespace App\Http\Middleware;

use App\Services\SiteSettingService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RequireSiteLogin
{
    // 사이트 설정(보안 탭)에서 "전체 사이트 로그인 필수"를 켜면, 프론트 전체(로그인/회원가입/
    // 비밀번호 재설정 등 인증 관련 라우트 제외)를 비회원이 접근할 수 없게 만든다("인트라넷형" 운영).
    // 게시판별 min_read_level 등 기존 권한 체계(BoardFrontController::denyIfBelowLevel())보다
    // 앞단에서 동작하는 레이어로 설계했다 — 이 설정이 꺼져 있으면(기본값) 기존처럼 게시판별 레벨
    // 설정만으로 접근을 제어하며, 이 미들웨어는 아무 영향도 주지 않는다.
    //
    // 로그인 자체가 막히면 안 되므로 로그인/회원가입/비밀번호 재설정 라우트에는 이 미들웨어를
    // 걸지 않는다(routes/web.php의 $frontRoutes 그룹에만 등록 — authExtraRoutes/SEO 라우트는
    // 별도 그룹이라 자동으로 제외됨). 배너 클릭/파일 다운로드도 같은 이유로 별도 그룹
    // ($siteLoginExemptRoutes)으로 빠져 있다 — 이메일/카톡 등 사이트 밖 채널로 공유되는 링크라
    // 인트라넷 모드에서도 비회원이 열 수 있어야 한다(2026-08-08 사용자 확인).
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            return $next($request);
        }

        try {
            $enabled = app(SiteSettingService::class)->get('site_login_required_enabled') === '1';
        } catch (Throwable) {
            // 설정 조회 실패 시 오늘까지의 기본 동작(비회원 열람 허용)을 그대로 유지한다 —
            // 설정 서비스 장애로 사이트 전체가 잠기는 사고를 막기 위함(SiteIpBlocklist와 동일 원칙).
            return $next($request);
        }

        if (! $enabled) {
            return $next($request);
        }

        return redirect()->guest(front_route('login'));
    }
}
