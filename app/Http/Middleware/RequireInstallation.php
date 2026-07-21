<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireInstallation
{
    // install.php가 설치 완료 시 생성하는 잠금 파일이 없으면(=아직 설치 전) install.php로 안내한다.
    // 헬스체크(/up)는 배포/모니터링 도구가 항상 성공을 기대하므로 예외로 둔다.
    // 테스트 환경(phpunit.xml에서 APP_ENV=testing)은 install.php 실행 없이 매번 새 SQLite
    // 인메모리 DB로 돌아가므로 installed.lock이 있을 수 없다 — 이 미들웨어 대상에서 제외한다.
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->environment('testing') && ! $request->is('up') && ! file_exists(storage_path('installed.lock'))) {
            return redirect('/install.php');
        }

        return $next($request);
    }
}
