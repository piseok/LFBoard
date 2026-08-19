<?php

namespace Tests\Feature\Auth;

use App\Http\Controllers\Auth\RegisterController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// User::$fillable includes level/admin_role/admin_permissions/is_active (needed for the admin
// panel's own forms), which looks risky in isolation. This test actually attempts the attack a
// malicious member would try — forging those fields into the profile-update request — to verify
// MyPageController's explicit editableFields() whitelist (not $fillable) is what actually gates
// what gets written, regardless of what a forged request body contains.
class PrivilegeEscalationTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_cannot_self_promote_to_admin_via_forged_profile_update_fields(): void
    {
        $member = User::create([
            'name' => 'Member', 'email' => 'member@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_MEMBER, 'is_active' => true,
        ]);

        $this->actingAs($member)->put('/mypage', [
            'name' => 'Member',
            'nickname' => '별명',
            'level' => User::LEVEL_ADMIN,
            'admin_role' => 'super',
            'admin_permissions' => ['users', 'boards', 'posts'],
            'is_active' => true,
        ]);

        $member->refresh();

        $this->assertSame(User::LEVEL_MEMBER, $member->level);
        $this->assertNull($member->admin_role);
        $this->assertNull($member->admin_permissions);
    }

    // RegisterRequest는 email:rfc,dns 규칙을 써서 실제 DNS 조회가 필요한데, 이 테스트 실행
    // 환경(Docker 컨테이너)에 외부 네트워크/DNS 접근이 없어 어떤 도메인을 넣어도 이메일 검증
    // 자체가 항상 실패한다(이 테스트가 확인하려는 것과 무관한 환경 제약). 그래서 HTTP 전체
    // 경로 대신, 이 취약점이 실제로 있을 수 있는 지점인 RegisterController::create()를 리플렉션으로
    // 직접 호출해 검증한다 — $data에 level/admin_role이 들어있어도(미래에 실수로 $request->all()을
    // 넘기게 바뀌는 경우를 가정) User::create()에 전달되는 배열에는 절대 반영되지 않아야 한다.
    public function test_forged_level_and_admin_role_in_registration_data_are_ignored(): void
    {
        $controller = new RegisterController;
        $method = new \ReflectionMethod(RegisterController::class, 'create');
        $method->setAccessible(true);

        $user = $method->invoke($controller, [
            'name' => 'Attacker',
            'email' => 'attacker@example.com',
            'password' => 'password123',
            'level' => User::LEVEL_ADMIN,
            'admin_role' => 'super',
            'admin_permissions' => ['users', 'boards'],
            'is_active' => false,
        ]);

        $this->assertSame(User::LEVEL_MEMBER, $user->level);
        $this->assertNull($user->admin_role);
        $this->assertNull($user->admin_permissions);
    }
}
