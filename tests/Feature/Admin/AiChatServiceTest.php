<?php

namespace Tests\Feature\Admin;

use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\User;
use App\Services\AiChatService;
use App\Services\SiteSettingService;
use App\Services\UploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

// AI 비서의 핵심 서비스 계층. 실제 OpenAI/Gemini API 키 없이도 Http::fake()로 응답을 흉내내
// 검증한다(실키 연동은 API 키를 넣은 뒤 실제로 확인 필요 — 여기서는 우리 쪽 로직만 검증).
class AiChatServiceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $email = 'admin@test.local'): User
    {
        return User::create([
            'name' => 'Admin', 'email' => $email, 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'super', 'is_active' => true,
        ]);
    }

    public function test_available_providers_is_empty_without_any_api_key(): void
    {
        $this->assertSame([], app(AiChatService::class)->availableProviders());
    }

    public function test_available_providers_includes_provider_once_key_is_set(): void
    {
        app(SiteSettingService::class)->set('ai_openai_api_key', 'sk-test-key', 'ai');

        $providers = app(AiChatService::class)->availableProviders();

        $this->assertArrayHasKey('openai', $providers);
        $this->assertArrayNotHasKey('gemini', $providers);
    }

    public function test_send_message_persists_conversation_and_records_audit_log(): void
    {
        app(SiteSettingService::class)->set('ai_openai_api_key', 'sk-test-key', 'ai');

        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => '안녕하세요, 도와드릴게요.']]],
            ], 200),
        ]);

        $admin = $this->admin();
        $message = app(AiChatService::class)->sendMessage($admin, null, 'openai', '안녕');

        $this->assertSame('안녕하세요, 도와드릴게요.', $message->content);
        $this->assertDatabaseHas('ai_chat_conversations', ['user_id' => $admin->id, 'provider' => 'openai']);
        $this->assertDatabaseHas('ai_chat_messages', ['role' => 'user', 'content' => '안녕']);
        $this->assertDatabaseHas('admin_audit_logs', ['admin_user_id' => $admin->id, 'action' => 'ai_chat']);
    }

    public function test_send_message_failure_is_logged_and_shows_friendly_error(): void
    {
        app(SiteSettingService::class)->set('ai_openai_api_key', 'sk-test-key', 'ai');

        Http::fake(['api.openai.com/*' => Http::response([], 500)]);

        $admin = $this->admin();
        $message = app(AiChatService::class)->sendMessage($admin, null, 'openai', '안녕');

        $this->assertNotSame('안녕하세요, 도와드릴게요.', $message->content);
        $log = \App\Models\AdminAuditLog::where('action', 'ai_chat')->first();
        $this->assertFalse($log->changes['success']);
    }

    public function test_generate_image_stores_file_and_message_with_image_path(): void
    {
        Storage::fake('uploads');
        app(SiteSettingService::class)->set('ai_openai_api_key', 'sk-test-key', 'ai');

        $pngBase64 = base64_encode(base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
        ));

        Http::fake([
            'api.openai.com/*' => Http::response(['data' => [['b64_json' => $pngBase64]]], 200),
        ]);

        $admin = $this->admin();
        $message = app(AiChatService::class)->generateImage($admin, null, 'openai', '팝업 배너 이미지');

        $this->assertNotNull($message->image_path);
        Storage::disk('uploads')->assertExists($message->image_path);
    }

    public function test_conversations_are_scoped_to_their_owner_when_resolving_existing_one(): void
    {
        app(SiteSettingService::class)->set('ai_openai_api_key', 'sk-test-key', 'ai');
        Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => 'ok']]]], 200)]);

        $adminA = $this->admin('a@test.local');
        $adminB = $this->admin('b@test.local');

        $first = app(AiChatService::class)->sendMessage($adminA, null, 'openai', '첫 메시지');
        $conversationId = $first->conversation_id;

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        app(AiChatService::class)->sendMessage($adminB, $conversationId, 'openai', '남의 대화에 끼어들기');
    }

    public function test_prune_expired_hard_deletes_old_conversations_and_their_images(): void
    {
        Storage::fake('uploads');
        app(SiteSettingService::class)->set('ai_chat_retention_days', '30', 'ai');

        $admin = $this->admin();
        $storedPath = app(UploadService::class)->uploadFromPath(
            $this->createTempPng(), 'ai_generated', 'png'
        );

        $old = AiChatConversation::create(['user_id' => $admin->id, 'provider' => 'openai', 'title' => 'old']);
        $old->forceFill(['created_at' => now()->subDays(90)])->save();
        AiChatMessage::create(['conversation_id' => $old->id, 'role' => 'assistant', 'image_path' => $storedPath]);

        $recent = AiChatConversation::create(['user_id' => $admin->id, 'provider' => 'openai', 'title' => 'recent']);
        AiChatMessage::create(['conversation_id' => $recent->id, 'role' => 'user', 'content' => 'hi']);

        app(AiChatService::class)->pruneExpired();

        $this->assertDatabaseMissing('ai_chat_conversations', ['id' => $old->id]);
        $this->assertDatabaseHas('ai_chat_conversations', ['id' => $recent->id]);
        Storage::disk('uploads')->assertMissing($storedPath);
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
