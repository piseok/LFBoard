<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\MaintenanceReports\MaintenanceReportResource;
use App\Filament\Resources\MaintenanceReports\Pages\CreateMaintenanceReport;
use App\Filament\Resources\MaintenanceReports\Pages\ListMaintenanceReports;
use App\Models\MaintenanceReport;
use App\Models\User;
use App\Services\SiteSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

// 유지보수 리포트는 원래 시스템 설정(최고관리자 전용) 그룹에 있었으나, 일반 최고관리자(client)가
// 직접 작성해 최고관리자에게 "보내는" 용도라 "운영 관리" 그룹(client + super 전용)으로 분리했다.
// 일반관리자(manager)는 접근 불가. 모든 client가 서로의 리포트를 볼 수 있고(전체공개), 한 번
// 전송된 리포트는 보고 기록 보존을 위해 client는 수정/삭제할 수 없으며 최고관리자만 예외적으로 가능하다.
class MaintenanceReportPermissionTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(string $email = 'super@test.local'): User
    {
        return User::create([
            'name' => 'Super', 'email' => $email, 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'super', 'is_active' => true,
        ]);
    }

    private function clientAdmin(string $email = 'client@test.local'): User
    {
        return User::create([
            'name' => 'Client', 'email' => $email, 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'client', 'is_active' => true,
        ]);
    }

    private function manager(string $email = 'manager@test.local'): User
    {
        return User::create([
            'name' => 'Manager', 'email' => $email, 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'manager',
            'admin_permissions' => ['posts'],
            'is_active' => true,
        ]);
    }

    public function test_manager_cannot_access_maintenance_reports_at_all(): void
    {
        $manager = $this->manager();

        $this->actingAs($manager)->get('/admin/maintenance-reports')->assertForbidden();
    }

    public function test_client_admin_can_access_and_create_reports(): void
    {
        $client = $this->clientAdmin();

        $this->actingAs($client)->get('/admin/maintenance-reports')->assertSuccessful();

        $this->actingAs($client);
        Livewire::test(CreateMaintenanceReport::class)
            ->fillForm([
                'title' => '로그인 버그 발견',
                'report_type' => 'bug',
                'content' => '로그인 시 간헐적으로 500 에러가 발생합니다.',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('maintenance_reports', [
            'title' => '로그인 버그 발견',
            'user_id' => $client->id,
        ]);
    }

    public function test_client_admins_can_see_reports_written_by_other_client_admins(): void
    {
        $author = $this->clientAdmin('author@test.local');
        $viewer = $this->clientAdmin('viewer@test.local');

        $report = MaintenanceReport::create([
            'user_id' => $author->id,
            'title' => '공유되는 리포트',
            'content' => '내용',
            'report_type' => 'notice',
        ]);

        $this->actingAs($viewer);

        Livewire::test(ListMaintenanceReports::class)
            ->assertCanSeeTableRecords([$report]);
    }

    public function test_client_admin_can_edit_and_delete_a_report_before_it_is_sent(): void
    {
        $client = $this->clientAdmin();
        $report = MaintenanceReport::create([
            'user_id' => $client->id,
            'title' => '초안',
            'content' => '내용',
            'report_type' => 'notice',
            'is_sent' => false,
        ]);

        $this->actingAs($client)->get("/admin/maintenance-reports/{$report->id}/edit")->assertSuccessful();

        Livewire::test(ListMaintenanceReports::class)
            ->callTableBulkAction('delete', [$report]);

        $this->assertDatabaseMissing('maintenance_reports', ['id' => $report->id]);
    }

    public function test_client_admin_cannot_edit_or_delete_a_report_once_sent(): void
    {
        $client = $this->clientAdmin();
        $report = MaintenanceReport::create([
            'user_id' => $client->id,
            'title' => '전송된 리포트',
            'content' => '내용',
            'report_type' => 'bug',
            'is_sent' => true,
        ]);

        $this->actingAs($client)->get("/admin/maintenance-reports/{$report->id}/edit")->assertForbidden();

        Livewire::test(ListMaintenanceReports::class)
            ->callTableBulkAction('delete', [$report]);

        $this->assertDatabaseHas('maintenance_reports', ['id' => $report->id]);
    }

    public function test_super_admin_can_still_edit_and_delete_a_sent_report(): void
    {
        $super = $this->superAdmin();
        $author = $this->clientAdmin();
        $report = MaintenanceReport::create([
            'user_id' => $author->id,
            'title' => '전송된 리포트',
            'content' => '내용',
            'report_type' => 'bug',
            'is_sent' => true,
        ]);

        $this->actingAs($super)->get("/admin/maintenance-reports/{$report->id}/edit")->assertSuccessful();

        Livewire::test(ListMaintenanceReports::class)
            ->callTableBulkAction('delete', [$report]);

        $this->assertDatabaseMissing('maintenance_reports', ['id' => $report->id]);
    }

    public function test_navigation_is_hidden_until_a_send_target_url_is_configured(): void
    {
        $this->actingAs($this->clientAdmin());

        $this->assertFalse(MaintenanceReportResource::shouldRegisterNavigation());
        $this->assertTrue(MaintenanceReportResource::canAccess());

        app(SiteSettingService::class)->set('maintenance_report_url', 'https://vendor.example.com/reports');

        $this->assertTrue(MaintenanceReportResource::shouldRegisterNavigation());
    }
}
