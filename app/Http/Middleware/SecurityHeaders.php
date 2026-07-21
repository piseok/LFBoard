<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // 요청마다 새 nonce를 발급해 뷰에 공유한다. 관리자가 site_settings의 head/body 스크립트에
        // 직접 붙여넣는 서드파티 스니펫(네이버 애널리틱스, GA 등)에 이 nonce를 부여해 CSP를 통과시키는 용도.
        $nonce = bin2hex(random_bytes(16));
        View::share('cspNonce', $nonce);

        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // HTTPS로 접속 중일 때만 HSTS를 내려준다(ConfigureSessionSecurity의 세션 secure 플래그
        // 자동감지와 동일한 방식 — HTTP로 개발 중인 환경에서 강제로 HTTPS만 접속하도록 만들면
        // 접속 자체가 안 되는 사고가 나므로, .env 고정값이 아니라 요청 시점 프로토콜로 판단한다).
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Filament 관리자 패널은 Livewire/Alpine이 자체 인라인 스크립트·스타일에 크게 의존하므로
        // CSP를 적용하면 정상 동작이 깨질 위험이 커서 관리자 패널 경로는 CSP 적용 대상에서 제외한다.
        if (! $request->is(config('app.admin_path', 'admin').'*')) {
            $response->headers->set(
                'Content-Security-Policy',
                "default-src 'self'; ".
                "script-src 'self' 'nonce-{$nonce}' https://www.googletagmanager.com; ".
                "style-src 'self' 'unsafe-inline'; ".
                "img-src 'self' data: https:; ".
                "frame-ancestors 'self';"
            );
        }

        return $response;
    }
}
