<?php

namespace Tests\Feature\Admin;

use App\Models\Inquiry;
use App\Models\User;
use App\Services\SiteSettingService;
use App\Services\UploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

// PruneAiChatHistoryTest와 동일한 패턴 — 관리자가 패널에 접속만 해도(하루 1회) 보유기간이
// 지난 1:1 문의가 첨부파일까지 완전히 삭제(파기)되어야 한다.
class PruneInquiriesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $email = 'super@test.local'): User
    {
        return User::create([
            'name' => 'Super', 'email' => $email, 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'super', 'is_active' => true,
        ]);
    }

    public function test_visiting_the_admin_panel_purges_expired_inquiries_and_their_attachments(): void
    {
        Storage::fake('uploads');
        app(SiteSettingService::class)->set('inquiry_retention_months', '36', 'general');

        $admin = $this->admin();
        $storedPath = app(UploadService::class)->uploadFromPath($this->createTempPng(), 'files', 'png');

        $old = Inquiry::create([
            'name' => '오래된 문의', 'title' => '제목', 'content' => '내용', 'file_path' => $storedPath,
        ]);
        $old->forceFill(['created_at' => now()->subMonths(40)])->save();

        $recent = Inquiry::create(['name' => '최근 문의', 'title' => '제목', 'content' => '내용']);

        $this->actingAs($admin)->get('/admin')->assertSuccessful();

        $this->assertDatabaseMissing('inquiries', ['id' => $old->id]);
        $this->assertDatabaseHas('inquiries', ['id' => $recent->id]);
        Storage::disk('uploads')->assertMissing($storedPath);
    }

    public function test_soft_deleted_expired_inquiries_are_also_purged(): void
    {
        app(SiteSettingService::class)->set('inquiry_retention_months', '36', 'general');

        $admin = $this->admin();
        $old = Inquiry::create(['name' => '오래된 문의', 'title' => '제목', 'content' => '내용']);
        $old->forceFill(['created_at' => now()->subMonths(40)])->save();
        $old->delete();

        $this->actingAs($admin)->get('/admin')->assertSuccessful();

        $this->assertDatabaseMissing('inquiries', ['id' => $old->id]);
    }

    public function test_retention_of_zero_or_less_keeps_inquiries_permanently(): void
    {
        app(SiteSettingService::class)->set('inquiry_retention_months', '0', 'general');

        $admin = $this->admin();
        $old = Inquiry::create(['name' => '오래된 문의', 'title' => '제목', 'content' => '내용']);
        $old->forceFill(['created_at' => now()->subYears(10)])->save();

        $this->actingAs($admin)->get('/admin')->assertSuccessful();

        $this->assertDatabaseHas('inquiries', ['id' => $old->id]);
    }

    public function test_prune_only_runs_once_per_day_even_across_multiple_requests(): void
    {
        app(SiteSettingService::class)->set('inquiry_retention_months', '36', 'general');

        $admin = $this->admin('super2@test.local');

        $this->actingAs($admin)->get('/admin')->assertSuccessful();

        $this->assertTrue(\Illuminate\Support\Facades\Cache::has('inquiry_prune_check_'.now()->format('Y-m-d')));

        $this->actingAs($admin)->get('/admin')->assertSuccessful();
    }

    private function createTempPng(): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'inquiry_file_test_').'.png';
        file_put_contents($tmp, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
        ));

        return $tmp;
    }
}
