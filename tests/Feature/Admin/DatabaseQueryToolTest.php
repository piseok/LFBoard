<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\DatabaseQueryTool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

// phpMyAdmin 대신 만든 최고관리자 전용 SQL 실행 도구. MySQL/MariaDB 버전과 무관하게 Laravel의
// 자체 DB 연결만 쓰므로 항상 동일하게 동작한다. 최고관리자 본인 책임하에 쓰는 기능이라 쿼리
// 종류를 제한하지 않지만, 모든 실행 내역(성공/실패 포함)은 감사로그에 남아야 한다.
class DatabaseQueryToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_cannot_access_the_tool(): void
    {
        $manager = User::create([
            'name' => 'Manager', 'email' => 'manager@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'manager', 'is_active' => true,
        ]);

        $this->actingAs($manager);

        $this->assertFalse(DatabaseQueryTool::canAccess());
    }

    public function test_super_admin_can_run_a_select_query_and_see_results(): void
    {
        $super = User::create([
            'name' => 'Super', 'email' => 'super@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'super', 'is_active' => true,
        ]);

        $this->actingAs($super);
        $this->assertTrue(DatabaseQueryTool::canAccess());

        Livewire::test(DatabaseQueryTool::class)
            ->fillForm(['sql' => "SELECT name, email FROM users WHERE email = 'super@test.local'"])
            ->call('run');

        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_user_id' => $super->id, 'action' => 'query',
        ]);
        $log = \App\Models\AdminAuditLog::where('action', 'query')->first();
        $this->assertTrue($log->changes['success']);
        $this->assertSame(1, $log->changes['affected_rows']);
    }

    public function test_super_admin_can_run_an_update_query(): void
    {
        $super = User::create([
            'name' => 'Super', 'email' => 'super@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'super', 'is_active' => true,
        ]);
        $target = User::create([
            'name' => 'Original', 'email' => 'target@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_MEMBER, 'is_active' => true,
        ]);

        $this->actingAs($super);

        Livewire::test(DatabaseQueryTool::class)
            ->fillForm(['sql' => "UPDATE users SET name = 'Updated' WHERE id = {$target->id}"])
            ->call('run');

        $this->assertSame('Updated', $target->fresh()->name);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'query']);
    }

    public function test_invalid_sql_shows_error_and_still_logs_the_attempt(): void
    {
        $super = User::create([
            'name' => 'Super', 'email' => 'super@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'super', 'is_active' => true,
        ]);

        $this->actingAs($super);

        Livewire::test(DatabaseQueryTool::class)
            ->fillForm(['sql' => 'SELECT * FROM this_table_does_not_exist'])
            ->call('run');

        $log = \App\Models\AdminAuditLog::where('action', 'query')->first();
        $this->assertNotNull($log);
        $this->assertFalse($log->changes['success']);
        $this->assertNotNull($log->changes['error']);
    }
}
