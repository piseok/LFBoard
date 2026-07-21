<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\SiteSettingService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AdminDebugMode
{
    // 공유호스팅에서는 관리자가 서버 로그에 접근하지 못하는 경우가 많아, 사이트 설정에서 "디버그 모드"를
    // 켜두면 관리자로 로그인한 상태에서만 Laravel의 상세 에러 페이지(스택트레이스 포함)를 볼 수 있게 한다.
    // 일반 방문자는 이 설정과 무관하게 항상 일반 에러 페이지만 본다(APP_DEBUG=false와 동일하게 안전).
    public function handle(Request $request, Closure $next): Response
    {
        $enabled = false;

        try {
            $enabled = app(SiteSettingService::class)->get('debug_mode_enabled') === '1';
        } catch (Throwable) {
            // site_settings 조회 실패 시 비활성으로 안전하게 처리
        }

        if ($enabled && $request->user()?->level === User::LEVEL_ADMIN) {
            config(['app.debug' => true]);
        }

        return $next($request);
    }
}
