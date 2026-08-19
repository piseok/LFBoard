<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\VendorNotices\VendorNoticeResource;
use App\Models\User;
use App\Models\VendorNotice;
use App\Services\SiteSettingService;
use App\Services\VendorNoticeSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

// "유지보수 리포트"(사이트 → 관리업체)와 반대 방향으로 관리업체 → 사이트 공지사항을 받아오는
// VendorNoticeSyncService/SyncVendorNotices 미들웨어/VendorNoticeResource 권한·배지를 검증한다.
class VendorNoticeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $email = 'admin@test.local', ?string $adminRole = 'super'): User
    {
        return User::create([
            'name' => 'Admin', 'email' => $email, 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => $adminRole, 'is_active' => true,
        ]);
    }

    public function test_sync_is_noop_when_disabled(): void
    {
        app(SiteSettingService::class)->set('vendor_notice_enabled', '0');

        $count = app(VendorNoticeSyncService::class)->sync();

        $this->assertSame(0, $count);
        $this->assertDatabaseCount('vendor_notices', 0);
    }

    public function test_sync_is_noop_without_api_url(): void
    {
        app(SiteSettingService::class)->set('vendor_notice_enabled', '1');
        app(SiteSettingService::class)->set('vendor_notice_api_url', '');

        $count = app(VendorNoticeSyncService::class)->sync();

        $this->assertSame(0, $count);
    }

    public function test_sync_fetches_and_upserts_notices(): void
    {
        app(SiteSettingService::class)->set('vendor_notice_enabled', '1');
        app(SiteSettingService::class)->set('vendor_notice_api_url', 'https://vendor.example.com/api/notices');
        app(SiteSettingService::class)->set('vendor_notice_api_token', 'secret-token');

        Http::fake([
            'vendor.example.com/*' => Http::response([
                'notices' => [
                    ['id' => 'n1', 'title' => '점검 안내', 'url' => 'https://vendor.example.com/n1', 'published_at' => '2026-07-18 10:00:00'],
                    ['id' => 'n2', 'title' => '업데이트 안내'],
                ],
            ], 200),
        ]);

        $newCount = app(VendorNoticeSyncService::class)->sync();

        $this->assertSame(2, $newCount);
        $this->assertDatabaseCount('vendor_notices', 2);
        $this->assertDatabaseHas('vendor_notices', ['external_id' => 'n1', 'title' => '점검 안내']);

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer secret-token'));
    }

    public function test_sync_upserts_without_duplicating_on_second_run(): void
    {
        app(SiteSettingService::class)->set('vendor_notice_enabled', '1');
        app(SiteSettingService::class)->set('vendor_notice_api_url', 'https://vendor.example.com/api/notices');

        Http::fake([
            'vendor.example.com/*' => Http::response([
                'notices' => [['id' => 'n1', 'title' => '점검 안내']],
            ], 200),
        ]);

        $first = app(VendorNoticeSyncService::class)->sync();
        $second = app(VendorNoticeSyncService::class)->sync();

        $this->assertSame(1, $first);
        $this->assertSame(0, $second);
        $this->assertDatabaseCount('vendor_notices', 1);
    }

    public function test_sync_skips_items_missing_id_or_title(): void
    {
        app(SiteSettingService::class)->set('vendor_notice_enabled', '1');
        app(SiteSettingService::class)->set('vendor_notice_api_url', 'https://vendor.example.com/api/notices');

        Http::fake([
            'vendor.example.com/*' => Http::response([
                'notices' => [
                    ['id' => '', 'title' => '아이디 없음'],
                    ['id' => 'n3', 'title' => ''],
                    ['id' => 'n4', 'title' => '정상 항목'],
                ],
            ], 200),
        ]);

        $newCount = app(VendorNoticeSyncService::class)->sync();

        $this->assertSame(1, $newCount);
        $this->assertDatabaseCount('vendor_notices', 1);
    }

    public function test_manager_admin_cannot_access_vendor_notice_resource(): void
    {
        app(SiteSettingService::class)->set('vendor_notice_enabled', '1');
        $manager = $this->admin('manager@test.local', 'manager');

        $response = $this->actingAs($manager)->get('/admin/vendor-notices');

        $response->assertForbidden();
    }

    public function test_client_admin_can_access_vendor_notice_resource(): void
    {
        $client = $this->admin('client@test.local', 'client');

        $response = $this->actingAs($client)->get('/admin/vendor-notices');

        $response->assertOk();
    }

    public function test_visiting_list_marks_notices_as_seen_and_clears_badge(): void
    {
        $admin = $this->admin();
        VendorNotice::create(['external_id' => 'n1', 'title' => '공지1']);
        $latest = VendorNotice::create(['external_id' => 'n2', 'title' => '공지2']);

        $this->actingAs($admin);
        $admin->refresh();
        $this->assertSame('2', \App\Filament\Resources\VendorNotices\VendorNoticeResource::getNavigationBadge());

        $this->actingAs($admin)->get('/admin/vendor-notices')->assertOk();

        $admin->refresh();
        $this->assertSame($latest->id, $admin->vendor_notice_last_seen_id);
        $this->assertNull(\App\Filament\Resources\VendorNotices\VendorNoticeResource::getNavigationBadge());
    }

    public function test_middleware_only_syncs_once_per_hour(): void
    {
        app(SiteSettingService::class)->set('vendor_notice_enabled', '1');
        app(SiteSettingService::class)->set('vendor_notice_api_url', 'https://vendor.example.com/api/notices');

        Http::fake([
            'vendor.example.com/*' => Http::response(['notices' => [['id' => 'n1', 'title' => '공지']]], 200),
        ]);

        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin')->assertOk();
        $this->assertDatabaseCount('vendor_notices', 1);

        // 같은 시간대(캐시 키가 시(hour) 단위) 안에서는 재요청해도 다시 동기화하지 않는다.
        Http::fake([
            'vendor.example.com/*' => Http::response(['notices' => [['id' => 'n2', 'title' => '공지2']]], 200),
        ]);
        $this->actingAs($admin)->get('/admin')->assertOk();
        $this->assertDatabaseCount('vendor_notices', 1);
    }

    public function test_navigation_is_hidden_until_sync_is_enabled_and_url_is_set(): void
    {
        $this->actingAs($this->admin());

        $this->assertFalse(VendorNoticeResource::shouldRegisterNavigation());
        $this->assertTrue(VendorNoticeResource::canAccess());

        app(SiteSettingService::class)->set('vendor_notice_enabled', '1');
        $this->assertFalse(VendorNoticeResource::shouldRegisterNavigation());

        app(SiteSettingService::class)->set('vendor_notice_api_url', 'https://vendor.example.com/api/notices');
        $this->assertTrue(VendorNoticeResource::shouldRegisterNavigation());
    }

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }
}
