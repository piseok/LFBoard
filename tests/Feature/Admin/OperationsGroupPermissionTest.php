<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\AdminAuditLogs\AdminAuditLogResource;
use App\Filament\Resources\AiChatLogs\AiChatLogResource;
use App\Filament\Resources\Policies\PolicyResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

// "운영 관리" 그룹(약관/방침, 관리자 활동로그, AI 대화로그, 유지보수 리포트 — 유지보수 리포트는
// MaintenanceReportPermissionTest에서 별도로 다룬다)은 최고관리자와 일반 최고관리자(client)에게만
// 열려 있고, 일반관리자(manager)는 admin_permissions 체크와 무관하게 항상 접근 불가해야 한다.
class OperationsGroupPermissionTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::create([
            'name' => 'Super', 'email' => 'super@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'super', 'is_active' => true,
        ]);
    }

    private function clientAdmin(): User
    {
        return User::create([
            'name' => 'Client', 'email' => 'client@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'client', 'is_active' => true,
        ]);
    }

    // 관리자 활동로그/AI 대화로그처럼 위험도 높은 화면 접근 권한을 전부 체크해도(모든 checkbox를
    // 켜도) 일반관리자는 admin_role 자체가 'manager'인 이상 절대 뚫을 수 없어야 한다.
    private function managerWithEveryPermission(): User
    {
        return User::create([
            'name' => 'Manager', 'email' => 'manager@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'manager',
            'admin_permissions' => ['dashboard', 'users', 'boards', 'posts', 'pages', 'popups', 'banners', 'inquiries', 'email_templates', 'media', 'marketing_mail', 'visit_stats', 'ai_assistant'],
            'is_active' => true,
        ]);
    }

    public static function operationsGroupResources(): array
    {
        return [
            'policies' => [PolicyResource::class],
            'admin audit logs' => [AdminAuditLogResource::class],
            'ai chat logs' => [AiChatLogResource::class],
        ];
    }

    #[DataProvider('operationsGroupResources')]
    public function test_super_admin_can_access(string $resourceClass): void
    {
        $this->actingAs($this->superAdmin())->get($resourceClass::getUrl())->assertSuccessful();
    }

    #[DataProvider('operationsGroupResources')]
    public function test_client_admin_can_access(string $resourceClass): void
    {
        $this->actingAs($this->clientAdmin())->get($resourceClass::getUrl())->assertSuccessful();
    }

    #[DataProvider('operationsGroupResources')]
    public function test_manager_cannot_access_even_with_every_other_permission_granted(string $resourceClass): void
    {
        $this->actingAs($this->managerWithEveryPermission())->get($resourceClass::getUrl())->assertForbidden();
    }
}
