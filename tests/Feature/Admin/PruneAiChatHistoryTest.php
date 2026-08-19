<?php

namespace Tests\Feature\Admin;

use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\User;
use App\Services\SiteSettingService;
use App\Services\UploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

// PruneAdminAuditLogs와 동일한 패턴 — 관리자가 패널에 접속만 해도(하루 1회) 보관 기간이
// 지난 AI 채팅 기록이 자동으로 정리되어야 한다. AiChatServiceTest는 서비스 메서드를 직접
// 호출해서 검증했지만, 여기서는 미들웨어가 실제로 걸려서 동작하는지 HTTP 요청으로 확인한다.
class PruneAiChatHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_visiting_the_admin_panel_prunes_expired_conversations_and_their_images(): void
    {
        Storage::fake('uploads');
        app(SiteSettingService::class)->set('ai_chat_retention_days', '30', 'ai');

        $admin = User::create([
            'name' => 'Super', 'email' => 'super@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'super', 'is_active' => true,
        ]);

        $storedPath = app(UploadService::class)->uploadFromPath(
            $this->createTempPng(), 'ai_generated', 'png'
        );

        $old = AiChatConversation::create(['user_id' => $admin->id, 'provider' => 'openai', 'title' => 'old']);
        $old->forceFill(['created_at' => now()->subDays(90)])->save();
        AiChatMessage::create(['conversation_id' => $old->id, 'role' => 'assistant', 'image_path' => $storedPath]);

        $recent = AiChatConversation::create(['user_id' => $admin->id, 'provider' => 'openai', 'title' => 'recent']);

        $this->actingAs($admin)->get('/admin')->assertSuccessful();

        $this->assertDatabaseMissing('ai_chat_conversations', ['id' => $old->id]);
        $this->assertDatabaseHas('ai_chat_conversations', ['id' => $recent->id]);
        Storage::disk('uploads')->assertMissing($storedPath);
    }

    public function test_prune_only_runs_once_per_day_even_across_multiple_requests(): void
    {
        app(SiteSettingService::class)->set('ai_chat_retention_days', '30', 'ai');

        $admin = User::create([
            'name' => 'Super', 'email' => 'super2@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'super', 'is_active' => true,
        ]);

        $this->actingAs($admin)->get('/admin')->assertSuccessful();

        // 방금 지나갔으니 오늘자 캐시 게이트가 이미 세팅되어 있어야 한다.
        $this->assertTrue(\Illuminate\Support\Facades\Cache::has('ai_chat_history_prune_check_'.now()->format('Y-m-d')));

        $this->actingAs($admin)->get('/admin')->assertSuccessful();
    }

    private function createTempPng(): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'ai_img_test_').'.png';
        file_put_contents($tmp, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
        ));

        return $tmp;
    }
}
