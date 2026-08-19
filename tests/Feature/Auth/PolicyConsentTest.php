<?php

namespace Tests\Feature\Auth;

use App\Models\Policy;
use App\Models\User;
use App\Services\PolicyScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyConsentTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_with_outdated_consent_is_redirected_to_policy_consent_screen(): void
    {
        Policy::create([
            'type' => 'terms', 'locale' => 'ko', 'title' => '이용약관', 'content' => '내용',
            'version' => '2.0', 'is_active' => true, 'is_required' => true,
        ]);

        $member = User::create([
            'name' => 'Member', 'email' => 'member@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_MEMBER, 'is_active' => true,
        ]);
        // 구버전에만 동의한 상태(전혀 동의 이력이 없는 것과 동일하게 취급되어야 함).
        $member->recordPolicyConsent('terms', 'ko', '1.0');

        $this->actingAs($member)->get('/mypage')->assertRedirect('/policy-consent');
    }

    public function test_member_with_current_consent_is_not_redirected(): void
    {
        Policy::create([
            'type' => 'terms', 'locale' => 'ko', 'title' => '이용약관', 'content' => '내용',
            'version' => '2.0', 'is_active' => true, 'is_required' => true,
        ]);

        $member = User::create([
            'name' => 'Member', 'email' => 'member@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_MEMBER, 'is_active' => true,
        ]);
        $member->recordPolicyConsent('terms', 'ko', '2.0');

        $this->actingAs($member)->get('/mypage')->assertSuccessful();
    }

    // 정회원(LEVEL_VERIFIED) 같은 추가 회원 등급도 "관리자가 아닌 가입 회원"이라 이 게이트가
    // 적용되어야 한다 — level이 정확히 LEVEL_MEMBER인지만 보던 예전 코드였다면 여기서 조용히
    // 빠졌을 회귀 시나리오.
    public function test_verified_member_tier_is_also_redirected_to_policy_consent_screen(): void
    {
        Policy::create([
            'type' => 'terms', 'locale' => 'ko', 'title' => '이용약관', 'content' => '내용',
            'version' => '2.0', 'is_active' => true, 'is_required' => true,
        ]);

        $verified = User::create([
            'name' => 'Verified', 'email' => 'verified@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_VERIFIED, 'is_active' => true,
        ]);

        $this->actingAs($verified)->get('/mypage')->assertRedirect('/policy-consent');
    }

    public function test_guest_and_admin_are_never_redirected_by_policy_consent_gate(): void
    {
        Policy::create([
            'type' => 'terms', 'locale' => 'ko', 'title' => '이용약관', 'content' => '내용',
            'version' => '2.0', 'is_active' => true, 'is_required' => true,
        ]);

        $this->get('/')->assertSuccessful();

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'super', 'is_active' => true,
        ]);
        $this->actingAs($admin)->get('/')->assertSuccessful();
    }

    public function test_submitting_the_consent_form_clears_the_block(): void
    {
        Policy::create([
            'type' => 'terms', 'locale' => 'ko', 'title' => '이용약관', 'content' => '내용',
            'version' => '2.0', 'is_active' => true, 'is_required' => true,
        ]);

        $member = User::create([
            'name' => 'Member', 'email' => 'member@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_MEMBER, 'is_active' => true,
        ]);

        // 실제 체크박스가 보내는 값은 문자열 "accepted"가 아니라 "1"이다(policy-consent.blade.php
        // 참고) — Laravel의 accepted 규칙은 1/true/on/yes만 통과시킨다.
        $this->actingAs($member)
            ->post('/policy-consent', ['policy_terms' => '1'])
            ->assertRedirect();

        $this->actingAs($member)->get('/mypage')->assertSuccessful();
        $this->assertDatabaseHas('policy_consents', [
            'user_id' => $member->id, 'type' => 'terms', 'version' => '2.0',
        ]);
    }

    public function test_scheduled_policy_change_applies_only_after_effective_date(): void
    {
        $policy = Policy::create([
            'type' => 'terms', 'locale' => 'ko', 'title' => '이용약관', 'content' => '원래 내용',
            'version' => '1.0', 'is_active' => true, 'is_required' => true,
            'pending_version' => '2.0', 'pending_content' => '변경될 내용',
            'effective_at' => now()->addDays(10),
        ]);

        $this->assertTrue($policy->hasPendingChange());

        $applied = app(PolicyScheduleService::class)->applyDueChanges();
        $this->assertSame(0, $applied, '시행일 전에는 아직 적용되면 안 됨');
        $this->assertSame('1.0', $policy->fresh()->version);

        $policy->update(['effective_at' => now()->subMinute()]);
        $applied = app(PolicyScheduleService::class)->applyDueChanges();
        $this->assertSame(1, $applied);

        $fresh = $policy->fresh();
        $this->assertSame('2.0', $fresh->version);
        $this->assertSame('변경될 내용', $fresh->content);
        $this->assertNull($fresh->pending_version);
        $this->assertNull($fresh->effective_at);
    }
}
