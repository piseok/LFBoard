<?php

namespace Tests\Feature\Admin;

use App\Models\AdminAuditLog;
use App\Services\AdminAuditLogService;
use App\Services\SiteSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuditLogPruneTest extends TestCase
{
    use RefreshDatabase;

    public function test_prune_removes_logs_older_than_the_retention_setting(): void
    {
        app(SiteSettingService::class)->set('admin_audit_log_retention_days', '30');

        $old = AdminAuditLog::create(['action' => 'created', 'auditable_type' => 'x', 'auditable_id' => 1]);
        $old->created_at = now()->subDays(40);
        $old->save();

        $recent = AdminAuditLog::create(['action' => 'created', 'auditable_type' => 'x', 'auditable_id' => 2]);

        app(AdminAuditLogService::class)->pruneExpired();

        $this->assertDatabaseMissing('admin_audit_logs', ['id' => $old->id]);
        $this->assertDatabaseHas('admin_audit_logs', ['id' => $recent->id]);
    }

    public function test_prune_keeps_everything_when_retention_is_zero(): void
    {
        app(SiteSettingService::class)->set('admin_audit_log_retention_days', '0');

        $old = AdminAuditLog::create(['action' => 'created', 'auditable_type' => 'x', 'auditable_id' => 1]);
        $old->created_at = now()->subYears(5);
        $old->save();

        app(AdminAuditLogService::class)->pruneExpired();

        $this->assertDatabaseHas('admin_audit_logs', ['id' => $old->id]);
    }

    public function test_prune_uses_a_365_day_default_when_setting_is_absent(): void
    {
        $old = AdminAuditLog::create(['action' => 'created', 'auditable_type' => 'x', 'auditable_id' => 1]);
        $old->created_at = now()->subDays(400);
        $old->save();

        $recent = AdminAuditLog::create(['action' => 'created', 'auditable_type' => 'x', 'auditable_id' => 2]);
        $recent->created_at = now()->subDays(200);
        $recent->save();

        app(AdminAuditLogService::class)->pruneExpired();

        $this->assertDatabaseMissing('admin_audit_logs', ['id' => $old->id]);
        $this->assertDatabaseHas('admin_audit_logs', ['id' => $recent->id]);
    }
}
