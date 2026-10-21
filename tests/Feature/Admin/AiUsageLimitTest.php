<?php

namespace Tests\Feature\Admin;

use App\Models\AiChatConversation;
use App\Models\AiChatUsage;
use App\Models\User;
use App\Services\Ai\AiBusyException;
use App\Services\Ai\AiLimitExceededException;
use App\Services\AiChatService;
use App\Services\AiUsageService;
use App\Services\SiteSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

// AI 비서 일일 한도 - 예전에는 한도가 아예 없었다(비용 폭주 위험). 단위는 제공자 호출 1회, 관리자별 + 사이트 전체,
// 대화를 지워도 줄지 않고, 호출 "직전에" 먼저 기록한 뒤 세어서 초과면 되돌리며, 초과 시 제공자는 호출하지 않는다.
class AiUsageLimitTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $email = 'admin@test.local'): User
    {
        return User::create([
            'name' => 'Admin', 'email' => $email, 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'super', 'is_active' => true,
        ]);
    }

    private function settings(array $values): void
    {
        foreach ($values as $key => $value) {
            app(SiteSettingService::class)->set($key, (string) $value, 'ai');
        }
    }

    private function fakeGeminiChat(): void
    {
        $this->settings(['ai_gemini_api_key' => 'gm-test-key']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => '답변']]]]],
        ])]);
    }

    public function test_default_limits_are_conservative_when_settings_are_missing_or_invalid(): void
    {
        $usage = app(AiUsageService::class);

        $this->assertSame(30, $usage->limit('chat', 'admin'));
        $this->assertSame(150, $usage->limit('chat', 'site'));
        $this->assertSame(5, $usage->limit('image', 'admin'));
        $this->assertSame(20, $usage->limit('image', 'site'));

        $this->settings(['ai_chat_daily_limit_per_admin' => 'abc', 'ai_image_daily_limit' => '', 'ai_chat_daily_limit' => '-5']);
        $this->assertSame(30, $usage->limit('chat', 'admin'));
        $this->assertSame(20, $usage->limit('image', 'site'));
        $this->assertSame(150, $usage->limit('chat', 'site'));

        $this->settings(['ai_chat_daily_limit_per_admin' => '7']);
        $this->assertSame(7, $usage->limit('chat', 'admin'));
    }

    public function test_over_the_per_admin_limit_is_rejected_without_calling_the_provider_or_saving_messages(): void
    {
        $this->fakeGeminiChat();
        $this->settings(['ai_chat_daily_limit_per_admin' => 2]);
        $admin = $this->admin();
        $service = app(AiChatService::class);

        $first = $service->sendMessage($admin, null, 'gemini', '하나');
        $service->sendMessage($admin, $first->conversation_id, 'gemini', '둘');

        try {
            $service->sendMessage($admin, $first->conversation_id, 'gemini', '셋');
            $this->fail('한도를 넘으면 예외가 나야 합니다');
        } catch (AiLimitExceededException $e) {
            $this->assertSame('admin', $e->scope);
            $this->assertSame(2, $e->limit);
            $this->assertStringContainsString('한도', $e->getMessage());
        }

        Http::assertSentCount(2);
        $this->assertDatabaseCount('ai_chat_usage', 2); // 초과한 시도의 기록은 되돌려졌다
        $this->assertSame(4, AiChatConversation::first()->messages()->count()); // 질문 2 + 답변 2, 세 번째 질문은 저장 안 됨
    }

    public function test_deleting_conversations_does_not_reset_the_count(): void
    {
        $this->fakeGeminiChat();
        $this->settings(['ai_chat_daily_limit_per_admin' => 1]);
        $admin = $this->admin();

        app(AiChatService::class)->sendMessage($admin, null, 'gemini', '하나');
        AiChatConversation::query()->forceDelete();

        $this->expectException(AiLimitExceededException::class);
        app(AiChatService::class)->sendMessage($admin, null, 'gemini', '삭제 후 다시');
    }

    public function test_site_wide_limit_blocks_every_admin(): void
    {
        $this->fakeGeminiChat();
        $this->settings(['ai_chat_daily_limit' => 3, 'ai_chat_daily_limit_per_admin' => 10]);
        $a = $this->admin('a@test.local');
        $b = $this->admin('b@test.local');
        $service = app(AiChatService::class);

        $service->sendMessage($a, null, 'gemini', 'a1');
        $service->sendMessage($a, null, 'gemini', 'a2');
        $service->sendMessage($b, null, 'gemini', 'b1');

        try {
            $service->sendMessage($b, null, 'gemini', 'b2');
            $this->fail('사이트 전체 한도를 넘으면 예외가 나야 합니다');
        } catch (AiLimitExceededException $e) {
            $this->assertSame('site', $e->scope);
        }

        Http::assertSentCount(3);
    }

    public function test_zero_blocks_that_kind_entirely(): void
    {
        $this->fakeGeminiChat();
        $this->settings(['ai_chat_daily_limit_per_admin' => 0]);

        $this->expectException(AiLimitExceededException::class);

        try {
            app(AiChatService::class)->sendMessage($this->admin(), null, 'gemini', '안녕');
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_image_and_chat_are_counted_separately(): void
    {
        Storage::fake('uploads');
        $this->settings(['ai_gemini_api_key' => 'gm-test-key', 'ai_image_daily_limit_per_admin' => 1]);
        $png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
        Http::fake([
            'generativelanguage.googleapis.com/v1beta/models/gemini-nano-banana-2.1:*' => Http::response([
                'candidates' => [['content' => ['parts' => [['inlineData' => ['mimeType' => 'image/png', 'data' => $png]]]]]],
            ]),
            'generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => '답']]]]]]),
        ]);
        $admin = $this->admin();
        $service = app(AiChatService::class);

        $image = $service->generateImage($admin, null, 'gemini', '구름');
        $this->assertNotNull($image->image_path);

        // 이미지 한도는 찼지만 질문은 아직 쓸 수 있다.
        $service->sendMessage($admin, $image->conversation_id, 'gemini', '안녕');

        $this->expectException(AiLimitExceededException::class);
        $service->generateImage($admin, $image->conversation_id, 'gemini', '구름 하나 더');
    }

    public function test_status_reports_remaining_without_calling_any_api(): void
    {
        Http::fake();
        $this->settings(['ai_chat_daily_limit_per_admin' => 5, 'ai_chat_daily_limit' => 4]);
        $admin = $this->admin();
        $usage = app(AiUsageService::class);

        AiChatUsage::create(['user_id' => $admin->id, 'provider' => 'gemini', 'kind' => 'chat', 'created_at' => now()]);
        AiChatUsage::create(['user_id' => $admin->id, 'provider' => 'gemini', 'kind' => 'chat', 'created_at' => now()]);

        $status = $usage->status($admin, 'chat');

        $this->assertSame(2, $status['admin_used']);
        $this->assertSame(5, $status['admin_limit']);
        $this->assertSame(2, $status['site_used']);
        $this->assertSame(4, $status['site_limit']);
        // 남은 횟수는 두 한도 중 더 적은 쪽(관리자 3 vs 사이트 2).
        $this->assertSame(2, $status['remaining']);
        $this->assertNull($status['blocked_by']);

        AiChatUsage::create(['user_id' => $admin->id, 'provider' => 'gemini', 'kind' => 'chat', 'created_at' => now()]);
        AiChatUsage::create(['user_id' => $admin->id, 'provider' => 'gemini', 'kind' => 'chat', 'created_at' => now()]);

        $status = $usage->status($admin, 'chat');
        $this->assertSame(0, $status['remaining']);
        $this->assertSame('site', $status['blocked_by']);

        Http::assertNothingSent();
    }

    public function test_yesterdays_usage_does_not_count_today(): void
    {
        $admin = $this->admin();
        AiChatUsage::create(['user_id' => $admin->id, 'provider' => 'gemini', 'kind' => 'chat', 'created_at' => now()->subDay()->startOfDay()->subMinute()]);

        $this->assertSame(0, app(AiUsageService::class)->used('chat', $admin));
        $this->assertSame(30, app(AiUsageService::class)->status($admin, 'chat')['remaining']);
    }

    public function test_a_second_request_from_the_same_admin_while_one_is_in_flight_is_refused_without_a_provider_call(): void
    {
        $this->fakeGeminiChat();
        $admin = $this->admin();

        $lock = Cache::lock("ai-inflight:chat:{$admin->id}", 30);
        $this->assertTrue($lock->get());

        try {
            app(AiChatService::class)->sendMessage($admin, null, 'gemini', '동시 요청');
            $this->fail('동시 요청은 거절되어야 합니다');
        } catch (AiBusyException) {
            Http::assertNothingSent();
            $this->assertDatabaseCount('ai_chat_usage', 0);
        } finally {
            $lock->release();
        }

        // 잠금이 풀리면 다시 보낼 수 있다.
        app(AiChatService::class)->sendMessage($admin, null, 'gemini', '이제 괜찮음');
        $this->assertDatabaseCount('ai_chat_usage', 1);
    }

    public function test_the_lock_is_released_even_when_the_provider_fails(): void
    {
        $this->settings(['ai_gemini_api_key' => 'gm-test-key']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([], 500)]);
        $admin = $this->admin();

        $reply = app(AiChatService::class)->sendMessage($admin, null, 'gemini', '실패할 요청');
        $this->assertNotEmpty($reply->content);

        // 실패한 호출도 1건으로 센다(제공자 쪽에서 과금될 수 있어 보수적으로) + 잠금은 풀려서 바로 다시 보낼 수 있다.
        $this->assertSame(1, app(AiUsageService::class)->used('chat', $admin));
        app(AiChatService::class)->sendMessage($admin, $reply->conversation_id, 'gemini', '다시');
        $this->assertSame(2, app(AiUsageService::class)->used('chat', $admin));
    }

    public function test_old_usage_rows_are_pruned_but_recent_ones_stay(): void
    {
        $admin = $this->admin();
        AiChatUsage::create(['user_id' => $admin->id, 'provider' => 'gemini', 'kind' => 'chat', 'created_at' => now()->subDays(60)]);
        AiChatUsage::create(['user_id' => $admin->id, 'provider' => 'gemini', 'kind' => 'chat', 'created_at' => now()->subDays(3)]);

        app(AiUsageService::class)->pruneOld(0);

        $this->assertDatabaseCount('ai_chat_usage', 1);
    }

    public function test_today_summary_groups_usage_by_admin(): void
    {
        $a = $this->admin('a@test.local');
        $b = $this->admin('b@test.local');
        foreach ([[$a, 'chat'], [$a, 'chat'], [$a, 'image'], [$b, 'chat']] as [$user, $kind]) {
            AiChatUsage::create(['user_id' => $user->id, 'provider' => 'gemini', 'kind' => $kind, 'created_at' => now()]);
        }

        $summary = app(AiUsageService::class)->todaySummary();

        $this->assertSame(3, $summary['chat_total']);
        $this->assertSame(1, $summary['image_total']);
        $this->assertSame(['name' => 'Admin', 'chat' => 2, 'image' => 1], $summary['rows'][0]);
    }
}
