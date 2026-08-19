<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\SiteSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 2023년 9월 개인정보보호위원회가 비밀번호 정기 변경 의무를 공식 폐지했으므로 법적 강제가 아니라
// 순수 선택 기능(사이트 설정 > 보안에서 on/off, 개월수 설정)이다. 약관 재동의와 달리 강제 차단이
// 아니라 "나중에 하기"로 넘길 수 있어야 한다.
class PasswordChangeReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_overdue_member_is_redirected_when_feature_is_enabled(): void
    {
        app(SiteSettingService::class)->set('password_change_reminder_enabled', '1');
        app(SiteSettingService::class)->set('password_change_reminder_months', '6');

        $member = User::create([
            'name' => 'Member', 'email' => 'member@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_MEMBER, 'is_active' => true,
            'password_changed_at' => now()->subMonths(7),
        ]);

        $this->actingAs($member)->get('/mypage')->assertRedirect('/password-reminder');
    }

    public function test_member_within_the_interval_is_not_redirected(): void
    {
        app(SiteSettingService::class)->set('password_change_reminder_enabled', '1');
        app(SiteSettingService::class)->set('password_change_reminder_months', '6');

        $member = User::create([
            'name' => 'Member', 'email' => 'member@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_MEMBER, 'is_active' => true,
            'password_changed_at' => now()->subMonths(3),
        ]);

        $this->actingAs($member)->get('/mypage')->assertSuccessful();
    }

    public function test_feature_disabled_never_redirects_even_when_overdue(): void
    {
        app(SiteSettingService::class)->set('password_change_reminder_enabled', '0');

        $member = User::create([
            'name' => 'Member', 'email' => 'member@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_MEMBER, 'is_active' => true,
            'password_changed_at' => now()->subYears(2),
        ]);

        $this->actingAs($member)->get('/mypage')->assertSuccessful();
    }

    public function test_dismissing_skips_the_reminder_for_the_rest_of_the_session(): void
    {
        app(SiteSettingService::class)->set('password_change_reminder_enabled', '1');
        app(SiteSettingService::class)->set('password_change_reminder_months', '6');

        $member = User::create([
            'name' => 'Member', 'email' => 'member@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_MEMBER, 'is_active' => true,
            'password_changed_at' => now()->subMonths(7),
        ]);

        $session = $this->actingAs($member);
        $session->get('/mypage')->assertRedirect('/password-reminder');
        $session->post('/password-reminder/dismiss')->assertRedirect();
        $session->get('/mypage')->assertSuccessful();
    }

    // 정회원(LEVEL_VERIFIED) 같은 추가 회원 등급도 이 알림 대상이어야 한다 — level이 정확히
    // LEVEL_MEMBER인지만 보던 예전 코드였다면 여기서 조용히 빠졌을 회귀 시나리오.
    public function test_verified_member_tier_is_also_covered_by_the_reminder(): void
    {
        app(SiteSettingService::class)->set('password_change_reminder_enabled', '1');
        app(SiteSettingService::class)->set('password_change_reminder_months', '6');

        $verified = User::create([
            'name' => 'Verified', 'email' => 'verified@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_VERIFIED, 'is_active' => true,
            'password_changed_at' => now()->subMonths(7),
        ]);

        $this->actingAs($verified)->get('/mypage')->assertRedirect('/password-reminder');
    }

    public function test_admin_and_guest_are_never_redirected(): void
    {
        app(SiteSettingService::class)->set('password_change_reminder_enabled', '1');
        app(SiteSettingService::class)->set('password_change_reminder_months', '6');

        $this->get('/')->assertSuccessful();

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'super', 'is_active' => true,
            'password_changed_at' => now()->subYears(2),
        ]);
        $this->actingAs($admin)->get('/')->assertSuccessful();
    }

    public function test_reminder_screen_strings_are_translated_for_every_supported_locale(): void
    {
        $strings = ['비밀번호 변경 안내', '지금 변경하기', '나중에 하기'];

        foreach (['ko', 'en', 'ja'] as $locale) {
            app()->setLocale($locale);
            foreach ($strings as $string) {
                $this->assertNotEmpty(__($string), "Missing {$locale} translation for: {$string}");
                if ($locale !== 'ko') {
                    $this->assertNotSame($string, __($string), "Untranslated (falls back to Korean) {$locale} string: {$string}");
                }
            }
        }
    }
}
