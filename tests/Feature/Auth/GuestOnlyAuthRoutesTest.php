<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 아이디/비밀번호 찾기는 비로그인 사용자를 위한 화면이라, 이미 로그인한 사용자는 접근할 이유가
// 없다(실사용자 발견 — 로그인 중에도 두 화면 다 그대로 들어가졌음). login/register처럼 guest
// 미들웨어로 막아 로그인 상태에서는 홈으로 리다이렉트되게 한다.
class GuestOnlyAuthRoutesTest extends TestCase
{
    use RefreshDatabase;

    private function member(): User
    {
        return User::create([
            'name' => '홍길동', 'email' => 'hong@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_MEMBER, 'is_active' => true,
        ]);
    }

    public function test_find_id_page_redirects_authenticated_users_away(): void
    {
        $this->actingAs($this->member())->get(front_route('find-id'))->assertRedirect();
    }

    public function test_find_id_submit_redirects_authenticated_users_away(): void
    {
        $this->actingAs($this->member())
            ->post(front_route('find-id.submit'), ['name' => '홍길동', 'email' => 'hong@test.local'])
            ->assertRedirect();
    }

    public function test_password_request_page_redirects_authenticated_users_away(): void
    {
        $this->actingAs($this->member())->get(front_route('password.request'))->assertRedirect();
    }

    public function test_password_reset_page_redirects_authenticated_users_away(): void
    {
        $this->actingAs($this->member())->get(front_route('password.reset', ['token' => 'anything']))->assertRedirect();
    }

    public function test_guests_can_still_reach_all_of_these_pages(): void
    {
        $this->get(front_route('find-id'))->assertOk();
        $this->get(front_route('password.request'))->assertOk();
    }
}
