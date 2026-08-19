<?php

namespace Tests\Feature\Auth;

use Illuminate\Routing\Router;
use Tests\TestCase;

// CSRF는 Laravel의 VerifyCsrfToken/ValidateCsrfToken 미들웨어가 "PHPUnit으로 유닛테스트를
// 돌리는 중"이면 자동으로 검증을 건너뛰도록 되어 있어(Illuminate\Foundation\Http\Middleware
// \VerifyCsrfToken::runningUnitTests()), $this->post(...) 같은 표준 테스트 클라이언트로는
// "토큰 없이 보내면 419가 나는지"를 아예 재현할 수 없다(항상 통과함). 그래서 실제로 살아있는
// 개발 서버에 curl로 직접 토큰 없이/있이 요청을 보내 확인했다(419 → 419, 유효 토큰 → 302 로그인
// 성공). 여기서는 대신 CI에서도 의미 있게 검증 가능한 것 — web 미들웨어 그룹에 CSRF 미들웨어가
// 실제로 등록돼 있는지 — 를 회귀 테스트로 남긴다(누군가 실수로 빼면 여기서 잡힘).
class CsrfProtectionTest extends TestCase
{
    public function test_csrf_middleware_is_registered_in_the_web_group(): void
    {
        $webGroup = app(Router::class)->getMiddlewareGroups()['web'] ?? [];

        $hasCsrf = collect($webGroup)->contains(
            fn ($middleware) => is_string($middleware) && str_contains($middleware, 'ValidateCsrfToken')
        );

        $this->assertTrue($hasCsrf, 'web 미들웨어 그룹에서 CSRF 검증 미들웨어가 제거된 것으로 보입니다.');
    }
}
