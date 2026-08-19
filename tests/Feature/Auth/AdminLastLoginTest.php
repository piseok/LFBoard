<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

// 관리자 패널(Filament) 로그인은 프론트 LoginController를 거치지 않아, 프론트에서만
// last_login_at을 갱신하던 예전 코드로는 관리자 계정의 "최근 로그인"이 영영 갱신되지 않았다
// (AppServiceProvider의 전역 Login 이벤트 리스너로 고침).
class AdminLastLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_last_login_at_is_updated_on_login_event(): void
    {
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'super', 'is_active' => true,
            'last_login_at' => null,
        ]);

        Auth::login($admin);

        $this->assertNotNull($admin->fresh()->last_login_at);
    }

    public function test_member_last_login_at_is_not_touched_by_the_admin_only_listener(): void
    {
        $member = User::create([
            'name' => 'Member', 'email' => 'member@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_MEMBER, 'is_active' => true, 'last_login_at' => null,
        ]);

        Auth::login($member);

        // 일반회원은 LoginController/SocialLoginController가 휴면계정 예외까지 포함해 이미
        // 직접 갱신하므로, 이 전역 리스너가 관여하지 않는지 확인한다(순수 Auth::login()만으로는
        // 프론트 컨트롤러 로직을 거치지 않으므로 여기선 갱신되지 않는 게 맞다).
        $this->assertNull($member->fresh()->last_login_at);
    }
}
