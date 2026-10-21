<?php

namespace Tests\Feature\Admin;

use App\Services\Ai\AiToolDeclaration;
use App\Services\Ai\FakeChatProvider;
use App\Services\Ai\GeminiProvider;
use App\Services\Ai\OpenAiProvider;
use App\Services\AiChatService;
use App\Services\SiteSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

// AI 비서 2.0 - 제공자 중립 인터페이스(chatWithTools)의 요청 형식과 응답 해석. 실제 API 는 부르지 않는다(유료 키) -
// 전부 Http::fake. 도구 호출(functionDeclarations/tools)은 실제 모델로 실측된 적이 없으므로 이 시험은 "우리가 보내는 모양"과
// "받은 응답을 해석하는 방식"만 고정한다(실측은 키 소유자가 최소 호출로).
class AiProviderToolsTest extends TestCase
{
    use RefreshDatabase;

    private function tools(): array
    {
        return [new AiToolDeclaration('propose_popup', '팝업 초안', [
            'type' => 'object',
            'properties' => ['title' => ['type' => 'string', 'description' => '제목']],
            'required' => ['title'],
        ])];
    }

    private function gemini(): GeminiProvider
    {
        app(SiteSettingService::class)->set('ai_gemini_api_key', 'gm-test-key', 'ai');

        return new GeminiProvider(app(SiteSettingService::class));
    }

    private function openai(): OpenAiProvider
    {
        app(SiteSettingService::class)->set('ai_openai_api_key', 'sk-test-key', 'ai');

        return new OpenAiProvider(app(SiteSettingService::class));
    }

    private const HISTORY = [['role' => 'user', 'content' => '팝업 만들어줘']];

    // ───────────── Gemini ─────────────

    public function test_gemini_sends_system_instruction_and_function_declarations_in_one_call(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => '네']]]]],
        ])]);

        $this->gemini()->chatWithTools('당신은 비서입니다', self::HISTORY, $this->tools());

        Http::assertSentCount(1);
        Http::assertSent(function ($request) {
            $body = $request->data();

            return $request->hasHeader('x-goog-api-key', 'gm-test-key')
                && ! str_contains($request->url(), 'key=')
                && $body['systemInstruction']['parts'][0]['text'] === '당신은 비서입니다'
                && $body['tools'][0]['functionDeclarations'][0]['name'] === 'propose_popup'
                && $body['tools'][0]['functionDeclarations'][0]['parameters']['type'] === 'object'
                && $body['toolConfig']['functionCallingConfig']['mode'] === 'AUTO'
                && $body['contents'][0]['role'] === 'user';
        });
    }

    public function test_gemini_plain_call_has_no_tools_or_system_instruction(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => '안녕']]]]],
        ])]);

        $this->assertSame('안녕', $this->gemini()->chat(self::HISTORY));

        Http::assertSent(fn ($request) => ! array_key_exists('tools', $request->data())
            && ! array_key_exists('systemInstruction', $request->data()));
    }

    public function test_gemini_collects_text_and_function_calls_from_all_parts(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [
                ['text' => '팝업 초안을 준비했어요.'],
                ['functionCall' => ['name' => 'propose_popup', 'args' => ['title' => '여름 이벤트']]],
                ['text' => '확인해 주세요.'],
            ]]]],
        ])]);

        $result = $this->gemini()->chatWithTools('', self::HISTORY, $this->tools());

        $this->assertSame("팝업 초안을 준비했어요.\n확인해 주세요.", $result->text);
        $this->assertCount(1, $result->toolCalls);
        $this->assertSame('propose_popup', $result->toolCalls[0]->name);
        $this->assertSame(['title' => '여름 이벤트'], $result->toolCalls[0]->args);
    }

    public function test_gemini_function_call_without_text_is_valid(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['functionCall' => ['name' => 'open_admin_page', 'args' => ['screen' => 'popups']]]]]]],
        ])]);

        $result = $this->gemini()->chatWithTools('', self::HISTORY, $this->tools());

        $this->assertSame('', $result->text);
        $this->assertSame('open_admin_page', $result->toolCalls[0]->name);
    }

    public function test_gemini_ignores_thought_parts(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [
                ['text' => '내부 사고 요약', 'thought' => true],
                ['text' => '최종 답변'],
            ]]]],
        ])]);

        $this->assertSame('최종 답변', $this->gemini()->chat(self::HISTORY));
    }

    public function test_gemini_empty_candidate_malformed_call_and_prompt_block_all_fail_without_retry(): void
    {
        $cases = [
            ['candidates' => []],
            ['candidates' => [['finishReason' => 'MALFORMED_FUNCTION_CALL']]],
            ['candidates' => [['finishReason' => 'SAFETY', 'content' => ['parts' => []]]]],
            ['promptFeedback' => ['blockReason' => 'SAFETY']],
        ];

        foreach ($cases as $body) {
            Http::fake(['generativelanguage.googleapis.com/*' => Http::response($body)]);

            try {
                $this->gemini()->chatWithTools('', self::HISTORY, $this->tools());
                $this->fail('예외가 나야 합니다: '.json_encode($body));
            } catch (RuntimeException) {
                $this->addToAssertionCount(1);
            }

            // 한 케이스당 호출은 정확히 1번 - 재시도 없음(재시도는 곧 비용).
            Http::assertSentCount(1);
        }
    }

    // ───────────── OpenAI ─────────────

    public function test_openai_sends_system_message_tools_and_disables_parallel_calls(): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['choices' => [['finish_reason' => 'stop', 'message' => ['content' => '네']]]])]);

        $this->openai()->chatWithTools('당신은 비서입니다', self::HISTORY, $this->tools());

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $body['messages'][0] === ['role' => 'system', 'content' => '당신은 비서입니다']
                && $body['messages'][1]['role'] === 'user'
                && $body['tools'][0]['type'] === 'function'
                && $body['tools'][0]['function']['name'] === 'propose_popup'
                && $body['parallel_tool_calls'] === false
                && $body['tool_choice'] === 'auto';
        });
    }

    public function test_openai_plain_call_has_no_tool_fields(): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => '안녕']]]])]);

        $this->assertSame('안녕', $this->openai()->chat(self::HISTORY));

        Http::assertSent(fn ($request) => ! array_key_exists('tools', $request->data())
            && ! array_key_exists('parallel_tool_calls', $request->data())
            && $request->data()['messages'][0]['role'] === 'user');
    }

    public function test_openai_parses_tool_call_arguments_json_string(): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['choices' => [[
            'finish_reason' => 'tool_calls',
            'message' => ['content' => null, 'tool_calls' => [[
                'id' => 'call_1', 'type' => 'function',
                'function' => ['name' => 'propose_popup', 'arguments' => '{"title":"여름 이벤트","duration_days":14}'],
            ]]],
        ]]])]);

        $result = $this->openai()->chatWithTools('', self::HISTORY, $this->tools());

        $this->assertSame('', $result->text);
        $this->assertSame('propose_popup', $result->toolCalls[0]->name);
        $this->assertSame(['title' => '여름 이벤트', 'duration_days' => 14], $result->toolCalls[0]->args);
    }

    public function test_openai_invalid_or_truncated_arguments_fail_without_retry(): void
    {
        $bad = [
            ['finish_reason' => 'tool_calls', 'message' => ['content' => null, 'tool_calls' => [['function' => ['name' => 'propose_popup', 'arguments' => '{"title": "잘림']]]]],
            ['finish_reason' => 'length', 'message' => ['content' => null, 'tool_calls' => [['function' => ['name' => 'propose_popup', 'arguments' => '{"title":"가능"}']]]]],
            ['finish_reason' => 'stop', 'message' => ['content' => null]],
        ];

        foreach ($bad as $choice) {
            Http::fake(['api.openai.com/*' => Http::response(['choices' => [$choice]])]);

            try {
                $this->openai()->chatWithTools('', self::HISTORY, $this->tools());
                $this->fail('예외가 나야 합니다');
            } catch (RuntimeException) {
                $this->addToAssertionCount(1);
            }

            Http::assertSentCount(1);
        }
    }

    // ───────────── 개발용 가짜 제공자 ─────────────

    public function test_fake_provider_exists_only_in_local_environment_with_the_explicit_flag(): void
    {
        $this->assertArrayNotHasKey('fake', app(AiChatService::class)->availableProviders());

        // 리뷰 L7: APP_ENV=local 만으로는 켜지지 않는다(운영에 APP_ENV=local 이 잘못 들어가도 키 없이 위젯이 노출되지 않게).
        $this->app['env'] = 'local';
        $this->assertFalse(config('services.ai.fake_provider'), '플래그 기본값은 꺼짐');
        $this->assertArrayNotHasKey('fake', app(AiChatService::class)->availableProviders());

        // 플래그만 켜고 환경이 local 이 아니어도 켜지지 않는다.
        $this->app['env'] = 'production';
        config(['services.ai.fake_provider' => true]);
        $this->assertArrayNotHasKey('fake', app(AiChatService::class)->availableProviders());

        $this->app['env'] = 'local';

        $this->assertArrayHasKey('fake', app(AiChatService::class)->availableProviders());
        $this->assertSame('개발용 가짜(도구 호출 흉내)', app(AiChatService::class)->availableProviders()['fake']);
    }

    public function test_fake_provider_is_deterministic_and_never_calls_the_network(): void
    {
        Http::fake();
        $fake = new FakeChatProvider;

        $popup = $fake->chatWithTools('', [['role' => 'user', 'content' => '팝업 만들어줘']], $this->tools());
        $this->assertSame('propose_popup', $popup->toolCalls[0]->name);
        $this->assertNotSame('', $popup->text);

        // 도구 선언이 없으면(사이트 설정에서 도구 호출을 껐거나 미지원) 텍스트만.
        $noTools = $fake->chatWithTools('', [['role' => 'user', 'content' => '팝업 만들어줘']], []);
        $this->assertSame([], $noTools->toolCalls);

        $this->expectException(RuntimeException::class);

        try {
            $fake->chat([['role' => 'user', 'content' => '오류 내줘']]);
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_fake_provider_makes_a_valid_png(): void
    {
        $path = (new FakeChatProvider)->generateImage('여름 바다');

        $this->assertStringEndsWith('.png', $path);
        $this->assertStringStartsWith('image/png', mime_content_type($path));
        @unlink($path);
    }
}
