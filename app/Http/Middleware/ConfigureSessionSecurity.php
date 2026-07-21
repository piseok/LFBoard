<?php

namespace App\Http\Middleware;

use App\Services\SiteSettingService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ConfigureSessionSecurity
{
    // 세션 쿠키의 secure 플래그를 요청 시점의 실제 접속 프로토콜에 맞춰 동적으로 결정한다.
    // install.php 실행 시점의 프로토콜로 .env에 고정된 값(SESSION_SECURE_COOKIE)에 의존하지 않으므로,
    // 개발 중 HTTP로 시작했다가 나중에 HTTPS로 전환해도 별도 조치 없이 자동으로 맞춰진다.
    // 관리자가 사이트 설정 > 보안 탭에서 "항상 사용"/"사용 안 함"으로 강제 지정할 수도 있다(리버스 프록시 등 자동감지가
    // 어려운 환경 대응용).
    public function handle(Request $request, Closure $next): Response
    {
        $mode = 'auto';

        try {
            $mode = app(SiteSettingService::class)->get('session_secure_cookie_mode', 'auto');
        } catch (Throwable) {
            // site_settings 테이블이 아직 없는 등 조회 실패 시 자동감지로 동작(안전한 기본값)
        }

        $secure = match ($mode) {
            'always' => true,
            'never' => false,
            default => $request->isSecure(),
        };

        config(['session.secure' => $secure]);

        return $next($request);
    }
}
