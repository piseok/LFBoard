<?php

namespace Tests\Feature\Admin;

use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\User;
use App\Services\SiteSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Mechanisms\HandleRequests\HandleRequests;
use Tests\TestCase;

// 사용자 테스트 결함 2("글쓰기 화면에서 필드에 채우기가 안 된다")의 재현·회귀 시험 - 실제 사용자 흐름 그대로:
//   글쓰기 화면을 연다(GET) → 위젯 아이콘을 누른다(Livewire 갱신 요청: toggle) → 대화를 고른다(selectConversation)
//   → 답변 아래에 "○○에 채우기" 버튼이 보여야 한다.
// 예전 코드는 현재 화면을 request()->is('admin/posts/create') 로 판별했는데 갱신 요청의 경로는 항상 livewire/update 라서
// 이 흐름에서 버튼이 한 번도 그려지지 않았다(이 시험은 그 코드에서 실패한다 - 2026-10-21 이전 dev 에서 확인).
// 위젯이 AI 2.0 이전 구조(공개 속성 이름이 달라도)와 무관하게 같은 흐름으로 시험하도록 일부러 공개 API 만 쓴다.
class AiChatWidgetUpdateRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_fill_buttons_appear_after_the_panel_is_opened_by_a_livewire_update_request(): void
    {
        app(SiteSettingService::class)->set('ai_openai_api_key', 'sk-test', 'ai');
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'super', 'is_active' => true,
        ]);
        $conversation = AiChatConversation::create(['user_id' => $admin->id, 'provider' => 'openai', 'title' => '글 요청']);
        AiChatMessage::create(['conversation_id' => $conversation->id, 'role' => 'user', 'content' => '글 써줘']);
        AiChatMessage::create(['conversation_id' => $conversation->id, 'role' => 'assistant', 'content' => '새 소식을 전합니다.']);

        // 1) 글쓰기 화면을 연다 - 위젯은 닫힌 채로 그려진다.
        $page = $this->actingAs($admin)->get('/admin/posts/create');
        $page->assertOk();

        preg_match_all('/wire:snapshot="([^"]*)"/', $page->getContent(), $matches);
        $snapshot = null;
        foreach ($matches[1] as $encoded) {
            $candidate = html_entity_decode($encoded, ENT_QUOTES);
            if ((json_decode($candidate, true)['memo']['name'] ?? null) === 'ai-chat-widget') {
                $snapshot = $candidate;
            }
        }
        $this->assertNotNull($snapshot, '페이지에 AI 비서 위젯이 있어야 합니다');

        // 2) 아이콘을 눌러 연다 + 대화를 고른다 - 둘 다 Livewire 갱신 요청이다(요청 경로는 livewire/update).
        $response = $this->actingAs($admin)->postJson(app(HandleRequests::class)->getUpdateUri(), [
            'components' => [[
                'snapshot' => $snapshot,
                'updates' => (object) [],
                'calls' => [
                    ['path' => '', 'method' => 'toggle', 'params' => []],
                    ['path' => '', 'method' => 'selectConversation', 'params' => [$conversation->id]],
                ],
            ]],
        ]);

        $response->assertOk();
        $html = (string) $response->json('components.0.effects.html');
        $this->assertStringContainsString('새 소식을 전합니다.', $html, '답변이 보여야 한다(대화가 선택됨)');
        $this->assertStringContainsString('제목에 채우기', $html, '글쓰기 화면에서는 답변 아래에 "제목에 채우기" 버튼이 보여야 한다');
        $this->assertStringContainsString('에 채우기', $html);
    }
}
