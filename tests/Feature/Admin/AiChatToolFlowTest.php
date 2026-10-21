<?php

namespace Tests\Feature\Admin;

use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\AiChatProposal;
use App\Models\AiChatUsage;
use App\Models\User;
use App\Services\Ai\AiLimitExceededException;
use App\Services\AiChatService;
use App\Services\SiteSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

// AI 비서 2.0 - 서비스 전체 흐름: 시스템 지시문·도구 선언을 한 번만 보내고, 응답의 텍스트+도구 호출을 모아 카드로 저장하며,
// 이력에는 카드를 평문 한 줄 요약으로만 남기고, 오류·차단은 재시도 없이 일반 문구로 내려가는지.
class AiChatToolFlowTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    private function admin(string $email = 'admin@test.local'): User
    {
        return User::create([
            'name' => 'Admin', 'email' => $email, 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'super', 'is_active' => true,
        ]);
    }

    // Http::fake() 를 다시 부르면 이전 가짜 응답 뒤에 "덧붙을" 뿐이라 먼저 등록한 응답이 계속 이긴다 - 새 팩토리로 바꿔 초기화한다.
    private function resetHttpFakes(): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
    }

    private function useGemini(): void
    {
        app(SiteSettingService::class)->set('ai_gemini_api_key', 'gm-test-key', 'ai');
    }

    private function geminiReply(array $parts): void
    {
        $this->useGemini();
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['parts' => $parts]]]])]);
    }

    private function lastBody(): array
    {
        $bodies = [];
        Http::assertSent(function (Request $request) use (&$bodies) {
            $bodies[] = $request->data();

            return true;
        });

        return end($bodies);
    }

    public function test_one_call_carries_system_instruction_and_tools_and_the_response_becomes_a_card(): void
    {
        $this->geminiReply([
            ['text' => '팝업 초안을 준비했어요.'],
            ['functionCall' => ['name' => 'propose_popup', 'args' => ['title' => '여름 휴가 안내', 'body' => '휴무입니다', 'start_offset_days' => 0, 'duration_days' => 14, 'image_prompt' => '바다']]],
        ]);
        $admin = $this->admin();

        $reply = app(AiChatService::class)->sendMessage($admin, null, 'gemini', '팝업 만들어줘', 'admin/popups');

        Http::assertSentCount(1); // 도구 결과를 되돌려 보내는 왕복이 없다
        $body = $this->lastBody();
        $system = $body['systemInstruction']['parts'][0]['text'];
        $this->assertStringContainsString('[현재 시각]', $system);
        $this->assertStringContainsString('popups: 팝업 관리', $system);
        $names = array_column($body['tools'][0]['functionDeclarations'], 'name');
        $this->assertEqualsCanonicalizing(['open_admin_page', 'propose_popup', 'propose_post', 'propose_page', 'propose_banner'], $names);

        $this->assertSame('팝업 초안을 준비했어요.', $reply->content);
        $this->assertCount(1, $reply->proposals);
        $card = $reply->proposals->first();
        $this->assertSame(AiChatProposal::KIND_POPUP, $card->kind);
        $this->assertSame('여름 휴가 안내', $card->payload['title']);
        $this->assertSame('image', $card->payload['content_type']);
        $this->assertTrue($card->needsImage());
        $this->assertSame($admin->id, $card->ownerId());
    }

    public function test_text_less_tool_call_gets_a_synthesized_reply_and_empty_content_is_never_stored(): void
    {
        $this->geminiReply([['functionCall' => ['name' => 'propose_popup', 'args' => ['title' => '글 없는 호출']]]]);

        $reply = app(AiChatService::class)->sendMessage($this->admin(), null, 'gemini', '팝업 만들어줘');

        $this->assertNotSame('', trim((string) $reply->content));
        $this->assertStringContainsString('팝업 초안', $reply->content);
        $this->assertCount(1, $reply->proposals);
    }

    public function test_invalid_tool_arguments_are_dropped_but_a_text_answer_still_arrives(): void
    {
        $this->geminiReply([
            ['text' => '이렇게 해보세요.'],
            ['functionCall' => ['name' => 'propose_popup', 'args' => ['title' => '']]], // 제목 없음 → 카드 버림
            ['functionCall' => ['name' => 'open_admin_page', 'args' => ['screen' => 'not_a_screen']]],
        ]);

        $reply = app(AiChatService::class)->sendMessage($this->admin(), null, 'gemini', '팝업');

        $this->assertSame('이렇게 해보세요.', $reply->content);
        $this->assertCount(0, $reply->proposals);
    }

    public function test_only_invalid_calls_and_no_text_gets_a_generic_fallback(): void
    {
        $this->geminiReply([['functionCall' => ['name' => 'propose_popup', 'args' => ['title' => '']]]]);

        $reply = app(AiChatService::class)->sendMessage($this->admin(), null, 'gemini', '팝업');

        $this->assertNotSame('', trim((string) $reply->content));
        $this->assertCount(0, $reply->proposals);
    }

    public function test_blocked_or_malformed_provider_response_gives_the_generic_error_without_retry(): void
    {
        $this->useGemini();
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['finishReason' => 'MALFORMED_FUNCTION_CALL']]])]);

        $reply = app(AiChatService::class)->sendMessage($this->admin(), null, 'gemini', '팝업 만들어줘');

        Http::assertSentCount(1);
        $this->assertStringContainsString('AI 응답을 받아오지 못했습니다', $reply->content);
        $this->assertCount(0, $reply->proposals);
        $log = \App\Models\AdminAuditLog::where('action', 'ai_chat')->first();
        $this->assertFalse($log->changes['success']);
    }

    public function test_turning_tools_off_in_site_settings_falls_back_to_plain_conversation(): void
    {
        $this->geminiReply([
            ['text' => '도구 없이 답합니다.'],
            ['functionCall' => ['name' => 'propose_popup', 'args' => ['title' => '무시되어야 함']]],
        ]);
        app(SiteSettingService::class)->set('ai_tools_enabled', '0', 'ai');

        $reply = app(AiChatService::class)->sendMessage($this->admin(), null, 'gemini', '팝업 만들어줘');

        $body = $this->lastBody();
        $this->assertArrayNotHasKey('tools', $body);
        $this->assertStringContainsString('도구를 쓸 수 없습니다', $body['systemInstruction']['parts'][0]['text']);
        $this->assertCount(0, $reply->proposals, '도구를 껐으면 모델이 호출을 보내도 카드를 만들지 않는다');
    }

    public function test_a_manager_without_permissions_never_sees_other_screens_in_the_prompt_or_tools(): void
    {
        $this->geminiReply([['text' => '답']]);
        $manager = User::create([
            'name' => 'Manager', 'email' => 'm@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'manager', 'admin_permissions' => ['popups', 'ai_assistant'], 'is_active' => true,
        ]);

        app(AiChatService::class)->sendMessage($manager, null, 'gemini', '안녕');

        $body = $this->lastBody();
        $system = $body['systemInstruction']['parts'][0]['text'];
        $this->assertStringContainsString('popups: 팝업 관리', $system);
        $this->assertStringNotContainsString('site_settings:', $system);
        $this->assertStringNotContainsString('posts: 게시글 관리', $system);
        $this->assertStringNotContainsString('db_query', $system);
        $names = array_column($body['tools'][0]['functionDeclarations'], 'name');
        $this->assertSame(['open_admin_page', 'propose_popup'], $names);
    }

    public function test_prompt_and_tools_never_contain_urls_or_the_admin_path(): void
    {
        config(['app.admin_path' => 'cms-secret-xyz']);
        $this->geminiReply([['text' => '답']]);
        $this->admin()->update(['name' => '홍길동', 'email' => 'private.person@example.com']);

        app(AiChatService::class)->sendMessage(User::first(), null, 'gemini', '안녕', 'cms-secret-xyz/posts/create');

        $json = json_encode($this->lastBody(), JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('cms-secret-xyz', $json);
        $this->assertStringNotContainsString('://', $json, 'URL 은 모델에게 보내지 않는다(키만 준다)');
        $this->assertStringNotContainsString('127.0.0.1', $json);
        $this->assertStringNotContainsString('홍길동', $json);
        $this->assertStringNotContainsString('private.person', $json);
    }

    public function test_history_replays_cards_as_one_plain_line_and_never_sends_empty_text(): void
    {
        $this->geminiReply([
            ['text' => '초안이에요.'],
            ['functionCall' => ['name' => 'propose_popup', 'args' => ['title' => '여름 휴가 안내']]],
        ]);
        $admin = $this->admin();
        $service = app(AiChatService::class);

        $first = $service->sendMessage($admin, null, 'gemini', '팝업 만들어줘');
        // 이미지만 있는 답변(content 가 null)도 이력에 섞여 있어야 한다.
        AiChatMessage::create(['conversation_id' => $first->conversation_id, 'role' => 'user', 'content' => '구름 그려줘']);
        AiChatMessage::create(['conversation_id' => $first->conversation_id, 'role' => 'assistant', 'content' => null, 'image_path' => 'uploads/ai_generated/x.png']);

        $this->resetHttpFakes();
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => '네']]]]]])]);
        $service->sendMessage($admin, $first->conversation_id, 'gemini', '고마워');

        $contents = $this->lastBody()['contents'];
        $this->assertSame('user', $contents[0]['role'], '이력은 항상 사용자 턴으로 시작한다');

        foreach ($contents as $turn) {
            $this->assertNotSame('', trim($turn['parts'][0]['text']), '빈 텍스트 파트는 제공자가 400 으로 거절한다');
        }

        $all = json_encode($contents, JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('[행동 카드: 팝업 초안 \"여름 휴가 안내\"]', $all);
        $this->assertStringContainsString('(이미지를 생성해 보여 드렸습니다)', $all);
        $this->assertStringNotContainsString('payload', $all);
        $this->assertStringNotContainsString('propose_popup', $all);

        // 같은 역할이 연달아 오면 합쳐진다(user, user → 한 턴).
        $roles = array_column($contents, 'role');
        for ($i = 1; $i < count($roles); $i++) {
            $this->assertNotSame($roles[$i - 1], $roles[$i], '같은 역할이 연달아 오면 안 된다');
        }
    }

    public function test_history_is_capped_to_the_most_recent_messages(): void
    {
        $this->geminiReply([['text' => '답']]);
        $admin = $this->admin();
        $conversation = AiChatConversation::create(['user_id' => $admin->id, 'provider' => 'gemini', 'title' => '긴 대화']);

        for ($i = 1; $i <= 60; $i++) {
            AiChatMessage::create(['conversation_id' => $conversation->id, 'role' => $i % 2 ? 'user' : 'assistant', 'content' => "메시지 {$i}"]);
        }

        app(AiChatService::class)->sendMessage($admin, $conversation->id, 'gemini', '마지막 질문');

        $json = json_encode($this->lastBody()['contents'], JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('메시지 1"', $json);
        $this->assertStringNotContainsString('메시지 20"', $json);
        $this->assertStringContainsString('마지막 질문', $json);
    }

    // ───────────── 카드의 이미지 생성 ─────────────

    private function proposalNeedingImage(User $admin): AiChatProposal
    {
        $this->geminiReply([
            ['text' => '초안'],
            ['functionCall' => ['name' => 'propose_popup', 'args' => ['title' => '이미지 팝업', 'image_prompt' => "푸른\n바다"]]],
        ]);

        return app(AiChatService::class)->sendMessage($admin, null, 'gemini', '팝업')->proposals->first();
    }

    private function fakeImageApi(): void
    {
        $this->resetHttpFakes();
        Http::fake([
            'generativelanguage.googleapis.com/v1beta/models/gemini-nano-banana-2.1:*' => Http::response([
                'candidates' => [['content' => ['parts' => [['inlineData' => ['mimeType' => 'image/png', 'data' => self::PNG]]]]]],
            ]),
            'generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => '답']]]]]]),
        ]);
    }

    public function test_proposal_image_is_generated_once_with_image_limit_and_single_line_prompt(): void
    {
        Storage::fake('uploads');
        $admin = $this->admin();
        $proposal = $this->proposalNeedingImage($admin);
        $this->fakeImageApi();

        $ready = app(AiChatService::class)->generateProposalImage($admin, $proposal->id);

        $this->assertSame(AiChatProposal::STATUS_IMAGE_READY, $ready->status);
        $this->assertStringStartsWith('uploads/ai_generated/', $ready->image_path);
        Storage::disk('uploads')->assertExists($ready->image_path);
        $this->assertDatabaseHas('ai_chat_usage', ['user_id' => $admin->id, 'kind' => 'image']);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'gemini-nano-banana-2.1')
            && $r['contents'][0]['parts'][0]['text'] === '푸른 바다');

        // 두 번째 호출은 이미 이미지가 있어 다시 만들지 않는다(비용 방지).
        $again = app(AiChatService::class)->generateProposalImage($admin, $proposal->id);
        $this->assertSame($ready->image_path, $again->image_path);
        $this->assertSame(1, \App\Models\AiChatUsage::where('kind', 'image')->count());
    }

    public function test_proposal_image_respects_the_image_limit_and_does_not_touch_the_proposal(): void
    {
        Storage::fake('uploads');
        $admin = $this->admin();
        $proposal = $this->proposalNeedingImage($admin);
        app(SiteSettingService::class)->set('ai_image_daily_limit_per_admin', '0', 'ai');
        $this->fakeImageApi();

        try {
            app(AiChatService::class)->generateProposalImage($admin, $proposal->id);
            $this->fail('이미지 한도 초과면 예외가 나야 합니다');
        } catch (AiLimitExceededException) {
            Http::assertNothingSent();
        }

        $this->assertNull($proposal->fresh()->image_path);
        $this->assertSame(AiChatProposal::STATUS_PROPOSED, $proposal->fresh()->status);
    }

    public function test_only_the_owner_can_generate_a_proposal_image_and_expired_cards_are_refused(): void
    {
        Storage::fake('uploads');
        $owner = $this->admin('owner@test.local');
        $other = $this->admin('other@test.local');
        $proposal = $this->proposalNeedingImage($owner);
        $this->fakeImageApi();

        try {
            app(AiChatService::class)->generateProposalImage($other, $proposal->id);
            $this->fail('남의 제안은 거절되어야 합니다');
        } catch (RuntimeException) {
            Http::assertNothingSent();
        }

        $proposal->update(['expires_at' => now()->subMinute()]);
        $this->expectException(RuntimeException::class);
        app(AiChatService::class)->generateProposalImage($owner, $proposal->id);
    }

    public function test_fake_provider_flow_end_to_end_in_local_environment_without_any_network(): void
    {
        Storage::fake('uploads');
        Http::fake();
        $this->app['env'] = 'local';
        $admin = $this->admin();
        $service = app(AiChatService::class);

        $reply = $service->sendMessage($admin, null, 'fake', '팝업 만들어줘');
        $card = $reply->proposals->first();
        $this->assertSame(AiChatProposal::KIND_POPUP, $card->kind);

        $ready = $service->generateProposalImage($admin, $card->id);
        Storage::disk('uploads')->assertExists($ready->image_path);

        // 가짜 호출도 한도에 센다(브라우저로 한도 흐름을 비용 없이 확인하려고).
        $this->assertSame(1, \App\Models\AiChatUsage::where('provider', 'fake')->where('kind', 'chat')->count());
        $this->assertSame(1, \App\Models\AiChatUsage::where('provider', 'fake')->where('kind', 'image')->count());

        Http::assertNothingSent();
    }

    // 보관 기간이 지나 대화를 지울 때: 저장에 쓰이지 않은(고아) 카드 이미지는 함께 지우고, 이미 팝업·배너·페이지가 쓰는(used) 이미지는 남긴다.
    public function test_prune_removes_orphan_card_images_but_keeps_images_used_by_saved_records(): void
    {
        Storage::fake('uploads');
        app(SiteSettingService::class)->set('ai_chat_retention_days', '30', 'ai');
        $admin = $this->admin();
        $conversation = AiChatConversation::create(['user_id' => $admin->id, 'provider' => 'gemini', 'title' => '오래된 대화']);
        $conversation->forceFill(['created_at' => now()->subDays(90)])->save();
        $message = AiChatMessage::create(['conversation_id' => $conversation->id, 'role' => 'assistant', 'content' => '카드']);

        foreach (['orphan' => AiChatProposal::STATUS_IMAGE_READY, 'kept' => AiChatProposal::STATUS_USED] as $name => $status) {
            Storage::disk('uploads')->put("uploads/ai_generated/2026/07/{$name}.png", 'x');
            AiChatProposal::create([
                'message_id' => $message->id, 'kind' => 'popup', 'payload' => ['title' => $name], 'status' => $status,
                'expires_at' => now()->subDays(80), 'created_at' => now()->subDays(80), 'image_path' => "uploads/ai_generated/2026/07/{$name}.png",
            ]);
        }

        AiChatUsage::create(['user_id' => $admin->id, 'provider' => 'gemini', 'kind' => 'chat', 'created_at' => now()->subDays(60)]);
        AiChatUsage::create(['user_id' => $admin->id, 'provider' => 'gemini', 'kind' => 'chat', 'created_at' => now()]);

        app(AiChatService::class)->pruneExpired();

        Storage::disk('uploads')->assertMissing('uploads/ai_generated/2026/07/orphan.png');
        Storage::disk('uploads')->assertExists('uploads/ai_generated/2026/07/kept.png');
        $this->assertDatabaseCount('ai_chat_proposals', 0); // 메시지와 함께 연쇄 삭제
        $this->assertDatabaseCount('ai_chat_conversations', 0);
        $this->assertSame(1, AiChatUsage::count(), '오래된 사용 기록만 정리되고 오늘 것은 남는다');
    }

    public function test_message_timestamps_use_the_app_timezone_not_the_database_default(): void
    {
        \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::create(2026, 10, 21, 14, 3, 0, 'Asia/Seoul'));
        $admin = $this->admin();
        $conversation = AiChatConversation::create(['user_id' => $admin->id, 'provider' => 'gemini', 'title' => '시각']);

        $message = AiChatMessage::create(['conversation_id' => $conversation->id, 'role' => 'user', 'content' => '안녕']);

        $this->assertSame('2026-10-21 14:03', $message->fresh()->created_at->format('Y-m-d H:i'));
    }

    public function test_fake_provider_is_refused_outside_local(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(AiChatService::class)->sendMessage($this->admin(), null, 'fake', '팝업 만들어줘');
    }
}
