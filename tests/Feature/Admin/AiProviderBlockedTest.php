<?php

namespace Tests\Feature\Admin;

use App\Livewire\AiChatWidget;
use App\Models\AdminAuditLog;
use App\Models\AiChatProposal;
use App\Models\AiChatUsage;
use App\Models\User;
use App\Services\Ai\AiBlockedException;
use App\Services\Ai\AiToolCall;
use App\Services\Ai\FakeChatProvider;
use App\Services\Ai\GeminiProvider;
use App\Services\Ai\OpenAiProvider;
use App\Services\AiChatService;
use App\Services\Ai\AiProposalService;
use App\Services\SiteSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

// 정책상 차단(2026-10-21 실호출 실측): 단순한 아이콘 프롬프트가 HTTP 200 + candidates[0].content 가 빈 배열 + finishReason
// IMAGE_RECITATION(finishMessage 동반)으로 와서 "응답을 해석할 수 없습니다"라는 불친절한 오류가 됐다. 이유별 안내를 보여 주고,
// 사유 코드만 노출하며(finishMessage·키·원문 응답 금지), 결과가 없는 차단 호출은 일일 한도를 소모하지 않는다.
class AiProviderBlockedTest extends TestCase
{
    use RefreshDatabase;

    private const FINISH_MESSAGE = 'Image generation stopped because the generated image is too similar to existing copyrighted works SECRET-DETAIL';

    private function admin(string $email = 'admin@test.local'): User
    {
        return User::create([
            'name' => 'Admin', 'email' => $email, 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'super', 'is_active' => true,
        ]);
    }

    private function gemini(): GeminiProvider
    {
        app(SiteSettingService::class)->set('ai_gemini_api_key', 'gm-SECRET-KEY', 'ai');

        return new GeminiProvider(app(SiteSettingService::class));
    }

    private function fakeGemini(array $candidate): void
    {
        app(SiteSettingService::class)->set('ai_gemini_api_key', 'gm-SECRET-KEY', 'ai');
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [$candidate]])]);
    }

    private function recitation(): array
    {
        // 실측한 모양 그대로: content 가 빈 배열, finishReason IMAGE_RECITATION, finishMessage 문자열.
        return ['content' => [], 'finishReason' => 'IMAGE_RECITATION', 'finishMessage' => self::FINISH_MESSAGE];
    }

    // ───────────── 제공자 ─────────────

    public function test_gemini_image_with_empty_content_and_image_recitation_becomes_a_friendly_blocked_error(): void
    {
        $this->fakeGemini($this->recitation());

        try {
            $this->gemini()->generateImage('단순한 파란 원 하나가 있는 작은 플랫 아이콘, 흰 배경');
            $this->fail('차단 예외가 나야 합니다');
        } catch (AiBlockedException $e) {
            $this->assertSame('IMAGE_RECITATION', $e->reasonCode);
            $this->assertSame('image', $e->kind);
            $this->assertStringContainsString('(IMAGE_RECITATION)', $e->getMessage());
            $this->assertStringContainsString('저작물', $e->getMessage());
            $this->assertStringContainsString('표현을 바꿔 다시 요청해 주세요', $e->getMessage());
            // 사유 코드만 노출한다 - finishMessage·키·원문 응답은 절대 안 나간다.
            $this->assertStringNotContainsString('SECRET-DETAIL', $e->getMessage());
            $this->assertStringNotContainsString('SECRET-KEY', $e->getMessage());
            $this->assertStringNotContainsString('copyrighted', $e->getMessage());
        }

        Http::assertSentCount(1); // 재시도 없음
    }

    public function test_each_policy_reason_gets_its_own_message_and_unknown_shapes_are_neutralised(): void
    {
        $cases = [
            'IMAGE_SAFETY' => '안전 정책',
            'SAFETY' => '안전 정책',
            'PROHIBITED_CONTENT' => '안전 정책',
            'IMAGE_PROHIBITED_CONTENT' => '안전 정책',
            'RECITATION' => '저작물',
            'IMAGE_OTHER' => '정책상 생성되지 않았습니다(IMAGE_OTHER)',
            'NO_IMAGE' => '정책상 생성되지 않았습니다(NO_IMAGE)',
        ];

        foreach ($cases as $reason => $expected) {
            $this->fakeGemini(['content' => [], 'finishReason' => $reason]);

            try {
                $this->gemini()->generateImage('x');
                $this->fail("{$reason} 는 차단 예외여야 합니다");
            } catch (AiBlockedException $e) {
                $this->assertSame($reason, $e->reasonCode);
                $this->assertStringContainsString($expected, $e->getMessage(), $reason);
                $this->assertStringContainsString("({$reason})", $e->getMessage());
            }
        }

        // 제공자가 보낸 임의 문자열이 화면으로 그대로 나가지 않는다.
        $e = AiBlockedException::forImage('IMAGE_<script>alert(1)</script>');
        $this->assertSame('UNKNOWN', $e->reasonCode);
        $this->assertStringNotContainsString('<script', $e->getMessage());
    }

    // 사용자 확정 기준: HTTP 200 + 이미지 없음 + finishReason 이 STOP 이 아니면 차단(한도 미소모) - 사유가 IMAGE_* 가 아니어도 마찬가지.
    public function test_any_non_stop_finish_reason_without_an_image_is_a_blocked_response(): void
    {
        foreach (['MAX_TOKENS', 'OTHER', 'FINISH_REASON_UNSPECIFIED', 'LANGUAGE'] as $reason) {
            $this->fakeGemini(['content' => [], 'finishReason' => $reason]);

            try {
                $this->gemini()->generateImage('x');
                $this->fail("{$reason} 는 차단 예외여야 합니다");
            } catch (AiBlockedException $e) {
                $this->assertSame($reason, $e->reasonCode);
            }
        }
    }

    public function test_image_without_a_finish_reason_or_with_stop_is_still_a_plain_failure_that_counts(): void
    {
        foreach ([
            ['content' => ['parts' => [['text' => '이미지를 만들 수 없습니다']]], 'finishReason' => 'STOP'],
            ['content' => []],
        ] as $candidate) {
            $this->fakeGemini($candidate);

            try {
                $this->gemini()->generateImage('x');
                $this->fail('실패 예외가 나야 합니다');
            } catch (AiBlockedException) {
                $this->fail('정책 차단이 아닌 응답을 차단으로 분류하면 안 된다: '.json_encode($candidate));
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('해석할 수 없습니다', $e->getMessage());
            }
        }
    }

    public function test_prompt_level_block_reason_is_a_blocked_error_for_image_and_chat(): void
    {
        app(SiteSettingService::class)->set('ai_gemini_api_key', 'gm-SECRET-KEY', 'ai');
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['promptFeedback' => ['blockReason' => 'PROHIBITED_CONTENT']])]);

        foreach (['generateImage' => 'image', 'chat' => 'chat'] as $method => $kind) {
            try {
                $method === 'chat' ? $this->gemini()->chat([['role' => 'user', 'content' => 'x']]) : $this->gemini()->generateImage('x');
                $this->fail('차단 예외가 나야 합니다');
            } catch (AiBlockedException $e) {
                $this->assertSame($kind, $e->kind);
                $this->assertSame('PROHIBITED_CONTENT', $e->reasonCode);
            }
        }
    }

    public function test_gemini_chat_safety_block_is_blocked_but_malformed_function_call_is_not(): void
    {
        $this->fakeGemini(['finishReason' => 'SAFETY']);
        try {
            $this->gemini()->chat([['role' => 'user', 'content' => 'x']]);
            $this->fail();
        } catch (AiBlockedException $e) {
            $this->assertSame('chat', $e->kind);
            $this->assertStringContainsString('답변이 정책상 생성되지 않았습니다(SAFETY)', $e->getMessage());
        }

        $this->fakeGemini(['finishReason' => 'MALFORMED_FUNCTION_CALL']);
        $this->expectException(RuntimeException::class);
        try {
            $this->gemini()->chat([['role' => 'user', 'content' => 'x']]);
        } catch (AiBlockedException) {
            $this->fail('형식 오류는 정책 차단이 아니다');
        }
    }

    public function test_openai_content_filter_and_image_policy_violation_are_blocked(): void
    {
        app(SiteSettingService::class)->set('ai_openai_api_key', 'sk-SECRET', 'ai');
        $openai = new OpenAiProvider(app(SiteSettingService::class));

        Http::fake(['api.openai.com/v1/chat/completions' => Http::response(['choices' => [['finish_reason' => 'content_filter', 'message' => ['content' => null]]]])]);
        try {
            $openai->chat([['role' => 'user', 'content' => 'x']]);
            $this->fail();
        } catch (AiBlockedException $e) {
            $this->assertSame('CONTENT_FILTER', $e->reasonCode);
        }

        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['api.openai.com/v1/images/generations' => Http::response(['error' => ['code' => 'content_policy_violation', 'message' => 'raw provider text']], 400)]);
        try {
            $openai->generateImage('x');
            $this->fail();
        } catch (AiBlockedException $e) {
            $this->assertSame('CONTENT_POLICY_VIOLATION', $e->reasonCode);
            $this->assertStringNotContainsString('raw provider text', $e->getMessage());
        }

        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['api.openai.com/v1/images/generations' => Http::response(['error' => ['code' => 'rate_limit_exceeded']], 429)]);
        $this->expectException(RuntimeException::class);
        try {
            $openai->generateImage('x');
        } catch (AiBlockedException) {
            $this->fail('속도 제한은 정책 차단이 아니다');
        }
    }

    // ───────────── 서비스: 한도·감사·안내 ─────────────

    public function test_a_blocked_image_shows_the_reason_in_the_conversation_and_does_not_use_the_daily_image_limit(): void
    {
        Storage::fake('uploads');
        $this->fakeGemini($this->recitation());
        $admin = $this->admin();
        app(SiteSettingService::class)->set('ai_image_daily_limit_per_admin', '1', 'ai');

        $message = app(AiChatService::class)->generateImage($admin, null, 'gemini', '단순한 파란 원 하나가 있는 작은 플랫 아이콘');

        $this->assertNull($message->image_path);
        $this->assertStringContainsString('(IMAGE_RECITATION)', $message->content);
        $this->assertStringNotContainsString('SECRET', $message->content);
        $this->assertSame(0, AiChatUsage::where('kind', 'image')->count(), '차단된 호출은 이미지 한도를 소모하지 않는다');

        $log = AdminAuditLog::where('action', 'ai_chat')->latest('id')->first();
        $this->assertFalse($log->changes['success']);
        $this->assertSame('IMAGE_RECITATION', $log->changes['blocked_reason']);
        $this->assertStringNotContainsString('SECRET', json_encode($log->changes));

        // 한도가 1장이어도 차단 뒤에 정상 생성이 가능하다.
        $png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['parts' => [['inlineData' => ['mimeType' => 'image/png', 'data' => $png]]]]]]])]);
        $ok = app(AiChatService::class)->generateImage($admin, $message->conversation_id, 'gemini', '다른 표현의 아이콘');
        $this->assertNotNull($ok->image_path);
        $this->assertSame(1, AiChatUsage::where('kind', 'image')->count());
    }

    // 사용자 확정 기준(java-cms 와 같음): 5xx·타임아웃·연결 오류·이미지 없는 STOP 응답은 비용이 들었을 수 있어 한도를 소모한다.
    public function test_server_errors_timeouts_and_stop_without_image_still_count_against_the_limit(): void
    {
        app(SiteSettingService::class)->set('ai_gemini_api_key', 'gm-SECRET-KEY', 'ai');
        $admin = $this->admin();
        $conversationId = null;
        $expected = 0;

        $scenarios = [
            'HTTP 500' => fn () => Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => 'boom'], 500)]),
            'HTTP 503' => fn () => Http::fake(['generativelanguage.googleapis.com/*' => Http::response([], 503)]),
            '타임아웃' => fn () => Http::fake(['generativelanguage.googleapis.com/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout')]),
            'STOP 인데 이미지 없음' => fn () => Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => '못 만들었어요']]], 'finishReason' => 'STOP']]])]),
        ];

        foreach ($scenarios as $label => $arrange) {
            Http::swap(new \Illuminate\Http\Client\Factory);
            $arrange();

            $message = app(AiChatService::class)->generateImage($admin, $conversationId, 'gemini', '아이콘');
            $conversationId = $message->conversation_id;
            $expected++;

            $this->assertStringContainsString('이미지 생성에 실패했습니다', $message->content, $label);
            $this->assertSame($expected, AiChatUsage::where('kind', 'image')->count(), "{$label}: 한도를 소모해야 합니다");
        }

        // 반대로 정책 차단은 같은 대화에서 이어서 시도해도 0 - 위 4건 뒤에도 늘지 않는다.
        $this->fakeGemini($this->recitation());
        app(AiChatService::class)->generateImage($admin, $conversationId, 'gemini', '정책 차단');
        $this->assertSame($expected, AiChatUsage::where('kind', 'image')->count());
    }

    public function test_a_blocked_card_image_keeps_the_proposal_and_does_not_use_the_limit(): void
    {
        Storage::fake('uploads');
        $admin = $this->admin();
        $this->fakeGemini(['content' => ['parts' => [['text' => '초안'], ['functionCall' => ['name' => 'propose_popup', 'args' => ['title' => '이미지 팝업', 'image_prompt' => '파란 원 아이콘']]]]]]);
        $proposal = app(AiChatService::class)->sendMessage($admin, null, 'gemini', '팝업')->proposals->first();
        $this->fakeGemini($this->recitation());
        $before = AiChatUsage::where('kind', 'image')->count();

        try {
            app(AiChatService::class)->generateProposalImage($admin, $proposal->id);
            $this->fail('차단 안내 예외가 나야 합니다');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('(IMAGE_RECITATION)', $e->getMessage());
            $this->assertStringContainsString('표현을 바꿔 다시 요청해 주세요', $e->getMessage());
        }

        $this->assertSame($before, AiChatUsage::where('kind', 'image')->count());
        $this->assertNull($proposal->fresh()->image_path);
        $this->assertSame(AiChatProposal::STATUS_PROPOSED, $proposal->fresh()->status, '제안은 그대로 - 이미지 없이 열 수 있다');
    }

    public function test_a_blocked_chat_answer_shows_the_reason_and_does_not_use_the_daily_question_limit(): void
    {
        $this->fakeGemini(['finishReason' => 'SAFETY']);
        $admin = $this->admin();

        $reply = app(AiChatService::class)->sendMessage($admin, null, 'gemini', '안녕');

        $this->assertStringContainsString('답변이 정책상 생성되지 않았습니다(SAFETY)', $reply->content);
        $this->assertSame(0, AiChatUsage::where('kind', 'chat')->count());
        $this->assertSame('SAFETY', AdminAuditLog::where('action', 'ai_chat')->latest('id')->first()->changes['blocked_reason']);

        // 형식 오류(MALFORMED_FUNCTION_CALL)는 정책 차단이 아니므로 일반 오류 문구 + 한도에 센다.
        $this->fakeGemini(['finishReason' => 'MALFORMED_FUNCTION_CALL']);
        $generic = app(AiChatService::class)->sendMessage($admin, $reply->conversation_id, 'gemini', '다시');
        $this->assertStringContainsString('AI 응답을 받아오지 못했습니다', $generic->content);
        $this->assertSame(1, AiChatUsage::where('kind', 'chat')->count());
    }

    // ───────────── 개발용 가짜 제공자 · 위젯 ─────────────

    public function test_fake_provider_can_simulate_blocking_for_browser_checks(): void
    {
        $fake = new FakeChatProvider;

        try {
            $fake->chat([['role' => 'user', 'content' => '이건 차단 되어야 해']]);
            $this->fail();
        } catch (AiBlockedException $e) {
            $this->assertSame('SAFETY', $e->reasonCode);
        }

        $this->expectException(AiBlockedException::class);
        $fake->generateImage('차단 테스트');
    }

    public function test_widget_shows_the_blocked_message_for_a_card_image_and_leaves_the_counts_alone(): void
    {
        $this->app['env'] = 'local';
        $admin = $this->admin();
        $this->actingAs($admin);
        // 가짜 제공자의 팝업 카드 이미지 프롬프트에는 "차단"이 없으므로, 카드의 프롬프트를 차단 유도 문구로 바꿔 둔다.
        $component = Livewire::test(AiChatWidget::class)->set('selectedProvider', 'fake')->set('isOpen', true)->set('input', '팝업 만들어줘')->call('send');
        $proposal = AiChatProposal::firstOrFail();
        $proposal->update(['payload' => array_merge($proposal->payload, ['image_prompt' => '차단되는 그림'])]);

        $component->call('openProposalWithImage', $proposal->id)
            ->assertNoRedirect()
            ->assertSee('(IMAGE_RECITATION)')
            ->assertSee('표현을 바꿔 다시 요청해 주세요');

        $this->assertSame(0, AiChatUsage::where('kind', 'image')->count());
        $this->assertNull($proposal->fresh()->image_path);
    }
}
