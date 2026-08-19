<?php

namespace Tests\Feature\Admin;

use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 관리자 접속 로그(admin_access_logs)를 감사로그(admin_audit_logs, action='access')로 합친 것의
// 회귀 테스트. 이전에는 접속 로그가 별도 테이블/화면/보관정책(7일 고정)이었다.
class AdminAccessLogMergeTest extends TestCase
{
    use RefreshDatabase;

    public function test_visiting_the_admin_panel_records_an_access_entry_in_the_audit_log(): void
    {
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'super', 'is_active' => true,
        ]);

        $this->actingAs($admin)->get('/admin')->assertSuccessful();

        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_user_id' => $admin->id,
            'admin_name' => 'Admin',
            'action' => 'access',
        ]);

        $log = AdminAuditLog::where('action', 'access')->first();
        $this->assertNotNull($log->ip);
        $this->assertNotNull($log->url);
        $this->assertSame('/admin', $log->auditable_label);
    }

    public function test_non_admin_visits_do_not_create_access_entries(): void
    {
        $member = User::create([
            'name' => 'Member', 'email' => 'member@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_MEMBER, 'is_active' => true,
        ]);

        $this->actingAs($member)->get('/mypage')->assertSuccessful();

        $this->assertDatabaseCount('admin_audit_logs', 0);
    }
}
