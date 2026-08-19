<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 세션/인증 쿠키는 Laravel의 EncryptCookies 미들웨어가 APP_KEY로 암호화+서명하므로, 값을
// 조작해도 복호화/MAC 검증에 실패해 "다른 계정으로 위장"이 성립하지 않아야 한다. 실제로
// 쿠키를 변조해서 시도해보는 회귀 테스트(코드를 읽고 "안전할 것"이라 추정하는 대신 직접 공격).
class CookieTamperingTest extends TestCase
{
    use RefreshDatabase;

    public function test_tampered_session_cookie_does_not_grant_access(): void
    {
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'super', 'is_active' => true,
        ]);
        $member = User::create([
            'name' => 'Member', 'email' => 'member@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_MEMBER, 'is_active' => true,
        ]);

        // 정상 로그인해서 진짜 암호화된 세션 쿠키를 발급받는다.
        $login = $this->post('/login', ['email' => 'member@test.local', 'password' => 'password']);
        $sessionCookieName = config('session.cookie');
        $realCookieValue = $login->headers->getCookies()[0]->getValue();

        foreach ($login->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $sessionCookieName) {
                $realCookieValue = $cookie->getValue();
            }
        }

        $this->assertNotEmpty($realCookieValue, '로그인 응답에 세션 쿠키가 없으면 이 테스트 자체가 의미 없음');

        // 쿠키 값을 조작해(끝 몇 글자를 뒤집어) 관리자 계정을 흉내내려는 시도.
        $tampered = strrev($realCookieValue);

        $response = $this->withCookie($sessionCookieName, $tampered)->get('/admin');

        // 위조된 쿠키는 복호화/MAC 검증에 실패해야 한다 — 정상 상태 코드(200)로 관리자 화면이
        // 그대로 열리면 안 된다. 정확히 어떤 실패 코드(419/403/302 등)로 막히는지는 Laravel
        // 내부 구현에 따라 달라질 수 있으므로, 여기서는 "관리자 화면이 실제로 열리지 않았다"는
        // 핵심 보안 속성만 확인한다.
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertDontSee('시스템 설정', false);
    }

    public function test_forged_cookie_with_another_users_id_does_not_authenticate_as_them(): void
    {
        $victim = User::create([
            'name' => 'Victim', 'email' => 'victim@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'super', 'is_active' => true,
        ]);

        // 공격자는 victim의 평문 세션 값(예: "login_web_...|3")을 안다고 가정하고, 그걸 그대로
        // (암호화 없이) 쿠키로 보내려는 시도 — EncryptCookies가 암호화된 값이 아니면 통째로 버려야 한다.
        $forgedPlaintextSessionValue = 'login_web_'.sha1('App\\Models\\User').'|'.$victim->id;

        $response = $this->withCookie(config('session.cookie'), $forgedPlaintextSessionValue)->get('/admin');

        $response->assertStatus(302);
    }
}
