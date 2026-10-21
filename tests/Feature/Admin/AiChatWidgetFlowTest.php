<?php

namespace Tests\Feature\Admin;

use App\Livewire\AiChatWidget;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\AiChatProposal;
use App\Models\AiChatUsage;
use App\Models\User;
use App\Services\SiteSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Livewire\Mechanisms\HandleRequests\HandleRequests;
use Tests\TestCase;

// AI 비서 2.0 위젯 - 사용자 테스트로 확인된 결함(2026-10-21)의 회귀 시험과 새 동작(한도·카드·잠금 속성·세션 유지).
//
// 결함 2 "글쓰기 화면에서 필드에 채우기가 안 된다"의 원인: 위젯이 request()->is('admin/posts/create') 로 현재 화면을 판별했는데,
// 위젯이 메시지를 보내고 답을 받는(그리고 패널을 여는) Livewire 갱신 요청의 경로는 항상 livewire/update 라서 첫 갱신
// 응답부터 화면 판별이 실패해 "채우기" 버튼이 아예 그려지지 않았다. 위젯은 열릴 때(toggle = 갱신 요청) 처음 그려지므로
// 한 번도 보이지 않는 구조였다. 아래 test_fill_buttons_survive_livewire_update_requests 가 실제 흐름(페이지 GET → 위젯 스냅샷
// → /livewire/update POST)으로 이를 재현·고정한다.
class AiChatWidgetFlowTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $email = 'admin@test.local'): User
    {
        return User::create([
            'name' => 'Admin', 'email' => $email, 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'super', 'is_active' => true,
        ]);
    }

    private function enableProvider(): void
    {
        app(SiteSettingService::class)->set('ai_openai_api_key', 'sk-test', 'ai');
    }

    private function conversationWithAnswer(User $admin, string $answer = "새 소식입니다.\n\n**굵게** 그리고 목록\n- 하나\n- 둘"): AiChatConversation
    {
        $conversation = AiChatConversation::create(['user_id' => $admin->id, 'provider' => 'openai', 'title' => '글 요청']);
        AiChatMessage::create(['conversation_id' => $conversation->id, 'role' => 'user', 'content' => '글 써줘']);
        AiChatMessage::create(['conversation_id' => $conversation->id, 'role' => 'assistant', 'content' => $answer]);

        return $conversation;
    }

    /** @return array{snapshot: string, id: string} 페이지 HTML 에서 위젯 컴포넌트의 스냅샷을 꺼낸다 */
    private function widgetSnapshot(string $html): array
    {
        preg_match_all('/wire:snapshot="([^"]*)"/', $html, $matches);

        foreach ($matches[1] as $encoded) {
            $snapshot = html_entity_decode($encoded, ENT_QUOTES);
            $decoded = json_decode($snapshot, true);

            if (($decoded['memo']['name'] ?? null) === 'ai-chat-widget') {
                return ['snapshot' => $snapshot, 'id' => $decoded['memo']['id']];
            }
        }

        $this->fail('페이지에 AI 비서 위젯이 렌더링되지 않았습니다');
    }

    // ───────────── 결함 2: 채우기 버튼 ─────────────

    public function test_fill_buttons_survive_livewire_update_requests(): void
    {
        $this->enableProvider();
        $admin = $this->admin();
        $conversation = $this->conversationWithAnswer($admin);
        $session = ['ai_chat_widget_open' => true, 'ai_chat_widget_conversation' => $conversation->id];

        // 1) 글쓰기 화면을 처음 열었을 때(마운트) - 원래 경로를 알고 있으므로 버튼이 있다.
        $page = $this->actingAs($admin)->withSession($session)->get('/admin/posts/create');
        $page->assertOk();
        $first = $this->widgetSnapshot($page->getContent());
        $this->assertSame('admin/posts/create', json_decode($first['snapshot'], true)['data']['screenPath']);
        $page->assertSee('제목에 채우기');

        // 2) 위젯의 Livewire 갱신 요청(요청 경로는 livewire/update) - 예전 코드는 여기서 버튼이 사라졌다.
        $uri = app(HandleRequests::class)->getUpdateUri();
        $this->assertStringNotContainsString('posts/create', $uri);

        $response = $this->actingAs($admin)->withSession($session)->postJson($uri, [
            'components' => [[
                'snapshot' => $first['snapshot'],
                'updates' => (object) [],
                'calls' => [['path' => '', 'method' => 'dismissNotice', 'params' => []]],
            ]],
        ]);

        $response->assertOk();
        $html = $response->json('components.0.effects.html');
        $this->assertIsString($html);
        $this->assertStringContainsString('제목에 채우기', $html, 'Livewire 갱신 응답에도 채우기 버튼이 있어야 한다');
        $this->assertStringContainsString('본문(에디터형 게시판)에 채우기', $html);
        $this->assertStringContainsString('본문(텍스트형 게시판)에 채우기', $html);

        // 3) 대상이 아닌 화면(목록)에서는 버튼이 없다.
        $list = $this->actingAs($admin)->withSession($session)->get('/admin/posts');
        $list->assertOk();
        $list->assertDontSee('제목에 채우기');
    }

    public function test_fill_dispatches_the_value_formatted_for_the_field_and_screen(): void
    {
        $this->enableProvider();
        $admin = $this->admin();
        $conversation = $this->conversationWithAnswer($admin);
        $answer = $conversation->messages()->where('role', 'assistant')->first();
        $this->actingAs($admin);

        $component = Livewire::test(AiChatWidget::class, ['screenPath' => 'admin/posts/create']);

        // 리치 에디터 칸 → 안전한 HTML, 한 줄 칸 → 첫 줄만(여러 문단 답이 제목에 통째로 들어가지 않는다), 평문 칸 → 서식 기호 없는 글.
        $component->call('fillTextField', $answer->id, 'content');
        $component->assertDispatched('fill-form-field', fn ($event, $params) => $params['field'] === 'content'
            && str_contains($params['value'], '<strong>굵게</strong>') && str_contains($params['value'], '<li>하나</li>'));

        $component->call('fillTextField', $answer->id, 'title');
        $component->assertDispatched('fill-form-field', fn ($event, $params) => $params['field'] === 'title' && $params['value'] === '새 소식입니다.');

        $component->call('fillTextField', $answer->id, 'content_text');
        $component->assertDispatched('fill-form-field', fn ($event, $params) => $params['field'] === 'content_text'
            && ! str_contains($params['value'], '**') && str_contains($params['value'], '- 하나'));
    }

    public function test_fill_is_refused_for_fields_outside_the_current_screens_allow_list_and_for_foreign_messages(): void
    {
        $this->enableProvider();
        $admin = $this->admin();
        $other = $this->admin('other@test.local');
        $mine = $this->conversationWithAnswer($admin)->messages()->where('role', 'assistant')->first();
        $theirs = $this->conversationWithAnswer($other)->messages()->where('role', 'assistant')->first();
        $this->actingAs($admin);

        $component = Livewire::test(AiChatWidget::class, ['screenPath' => 'admin/popups/create']);
        $component->call('fillTextField', $mine->id, 'is_active')
            ->call('fillTextField', $mine->id, 'content') // 팝업 폼에는 없는 칸
            ->assertNotDispatched('fill-form-field');

        // 대상 화면이 아니면 아무것도 보내지 않는다.
        Livewire::test(AiChatWidget::class, ['screenPath' => 'admin/popups'])
            ->call('fillTextField', $mine->id, 'title')
            ->assertNotDispatched('fill-form-field');

        // 남의 메시지는 찾을 수 없다.
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        Livewire::test(AiChatWidget::class, ['screenPath' => 'admin/popups/create'])->call('fillTextField', $theirs->id, 'title');
    }

    public function test_use_image_only_works_on_screens_with_an_image_field_and_only_for_own_messages(): void
    {
        $this->enableProvider();
        $admin = $this->admin();
        $conversation = AiChatConversation::create(['user_id' => $admin->id, 'provider' => 'openai', 'title' => '이미지']);
        $image = AiChatMessage::create(['conversation_id' => $conversation->id, 'role' => 'assistant', 'image_path' => 'uploads/ai_generated/2026/10/a.png']);
        $this->actingAs($admin);

        Livewire::test(AiChatWidget::class, ['screenPath' => 'admin/posts/create'])
            ->call('useImage', $image->id)
            ->assertNotDispatched('fill-form-field');

        Livewire::test(AiChatWidget::class, ['screenPath' => 'admin/popups/create'])
            ->call('useImage', $image->id)
            ->assertDispatched('fill-form-field', field: 'image_path', value: 'uploads/ai_generated/2026/10/a.png');
    }

    // ───────────── 잠금 속성 · 권한 ─────────────

    public function test_server_decided_properties_cannot_be_changed_from_the_browser(): void
    {
        $this->enableProvider();
        $this->actingAs($this->admin());
        $component = Livewire::test(AiChatWidget::class);

        foreach (['providers' => ['openai' => 'x'], 'screenPath' => 'admin/popups/create'] as $property => $value) {
            try {
                $component->set($property, $value);
                $this->fail("{$property} 는 잠겨 있어야 합니다");
            } catch (CannotUpdateLockedPropertyException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_an_admin_without_the_ai_permission_cannot_use_any_action(): void
    {
        $this->enableProvider();
        $manager = User::create([
            'name' => 'Manager', 'email' => 'm@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'manager', 'admin_permissions' => [], 'is_active' => true,
        ]);
        $this->actingAs($manager);

        Livewire::test(AiChatWidget::class)
            ->set('input', '안녕')
            ->call('send')
            ->assertForbidden();

        $this->assertDatabaseCount('ai_chat_usage', 0);
        $this->assertDatabaseCount('ai_chat_conversations', 0);
    }

    // ───────────── 보내기 · 카드 · 한도 ─────────────

    public function test_sending_with_the_fake_provider_creates_a_card_and_updates_the_remaining_counts(): void
    {
        $this->app['env'] = 'local';
        $admin = $this->admin();
        $this->actingAs($admin);

        $component = Livewire::test(AiChatWidget::class, ['screenPath' => 'admin/dashboard'])
            ->set('selectedProvider', 'fake')
            ->set('isOpen', true)
            ->set('input', '팝업 만들어줘')
            ->call('send')
            ->assertSet('input', '')
            ->assertSee('팝업 초안')
            ->assertSee('이미지 만들고 작성 화면 열기')
            ->assertSee('이미지 없이 열기')
            ->assertSee('저장은 되지 않습니다')
            ->assertSeeText('오늘 남은 질문 29회')
            ->assertSeeText('이미지 5장')
            ->assertSee('우리 사이트의 일일 한도 기준');

        $proposal = AiChatProposal::first();
        $this->assertNotNull($proposal);
        $this->assertSame($admin->id, $proposal->ownerId());
        $component->assertSee('/popups/create?ai_proposal='.$proposal->id, false);
    }

    public function test_a_rejected_send_keeps_the_typed_text_and_shows_the_limit_notice_without_creating_anything(): void
    {
        $this->app['env'] = 'local';
        app(SiteSettingService::class)->set('ai_chat_daily_limit_per_admin', '0', 'ai');
        $this->actingAs($this->admin());

        Livewire::test(AiChatWidget::class)
            ->set('selectedProvider', 'fake')
            ->set('isOpen', true)
            ->set('input', '팝업 만들어줘')
            ->call('send')
            ->assertSet('input', '팝업 만들어줘')
            ->assertSee('한도')
            ->assertSee('오늘 내 질문 한도를 모두 썼습니다');

        $this->assertDatabaseCount('ai_chat_conversations', 0);
        $this->assertDatabaseCount('ai_chat_messages', 0);
        $this->assertDatabaseCount('ai_chat_usage', 0);
    }

    public function test_remaining_counts_come_from_usage_rows_and_do_not_grow_back_when_conversations_are_deleted(): void
    {
        $this->app['env'] = 'local';
        $admin = $this->admin();
        $this->actingAs($admin);
        app(SiteSettingService::class)->set('ai_chat_daily_limit_per_admin', '2', 'ai');

        $component = Livewire::test(AiChatWidget::class)->set('selectedProvider', 'fake')->set('isOpen', true);
        $component->set('input', '하나')->call('send')->assertSeeText('오늘 남은 질문 1회');

        AiChatConversation::query()->forceDelete();
        $component->set('activeConversationId', null)->call('$refresh')->assertSeeText('오늘 남은 질문 1회');

        $component->set('input', '둘')->call('send')->assertSeeText('오늘 남은 질문 0회')->assertSee('오늘 내 질문 한도를 모두 썼습니다');
        $this->assertSame(2, AiChatUsage::count());
    }

    public function test_image_card_button_generates_one_image_and_redirects_to_the_create_screen_with_the_proposal(): void
    {
        Storage::fake('uploads');
        $this->app['env'] = 'local';
        $admin = $this->admin();
        $this->actingAs($admin);

        $component = Livewire::test(AiChatWidget::class)
            ->set('selectedProvider', 'fake')
            ->set('isOpen', true)
            ->set('input', '팝업 만들어줘')
            ->call('send');
        $proposal = AiChatProposal::firstOrFail();

        $component->call('openProposalWithImage', $proposal->id)
            ->assertRedirect(route('filament.admin.resources.popups.create', ['ai_proposal' => $proposal->id]));

        $this->assertSame(AiChatProposal::STATUS_IMAGE_READY, $proposal->fresh()->status);
        Storage::disk('uploads')->assertExists($proposal->fresh()->image_path);
        $this->assertSame(1, AiChatUsage::where('kind', 'image')->count());
    }

    public function test_image_card_button_shows_a_notice_when_the_image_limit_is_used_up(): void
    {
        $this->app['env'] = 'local';
        app(SiteSettingService::class)->set('ai_image_daily_limit_per_admin', '0', 'ai');
        $this->actingAs($this->admin());

        $component = Livewire::test(AiChatWidget::class)
            ->set('selectedProvider', 'fake')
            ->set('isOpen', true)
            ->set('input', '팝업 만들어줘')
            ->call('send');
        $proposal = AiChatProposal::firstOrFail();

        // 이미지 한도가 0 이면 버튼은 비활성으로 그려지고, 강제로 호출해도 이미지를 만들지 않는다.
        $component->assertSeeHtml('이미지 만들고 작성 화면 열기')
            ->assertSee('오늘 이미지 한도를 모두 썼습니다')
            ->call('openProposalWithImage', $proposal->id)
            ->assertNoRedirect()
            ->assertSee('한도');

        $this->assertNull($proposal->fresh()->image_path);
    }

    public function test_another_admin_cannot_open_or_generate_my_card(): void
    {
        Storage::fake('uploads');
        $this->app['env'] = 'local';
        $owner = $this->admin('owner@test.local');
        $this->actingAs($owner);
        Livewire::test(AiChatWidget::class)->set('selectedProvider', 'fake')->set('input', '팝업 만들어줘')->call('send');
        $proposal = AiChatProposal::firstOrFail();

        $this->actingAs($this->admin('other@test.local'));
        Livewire::test(AiChatWidget::class)
            ->set('isOpen', true)
            ->call('openProposalWithImage', $proposal->id)
            ->assertNoRedirect()
            ->assertSee('제안을 찾을 수 없거나 만료되었습니다');

        $this->assertNull($proposal->fresh()->image_path);
        $this->assertSame(0, AiChatUsage::where('kind', 'image')->count());
    }

    // ───────────── 표시 · 세션 ─────────────

    public function test_open_state_and_active_conversation_survive_a_full_page_load_via_the_session(): void
    {
        $this->enableProvider();
        $admin = $this->admin();
        $conversation = $this->conversationWithAnswer($admin);
        $this->actingAs($admin);

        Livewire::test(AiChatWidget::class)->call('toggle')->call('selectConversation', $conversation->id);

        $this->assertTrue(session('ai_chat_widget_open'));
        $this->assertSame($conversation->id, session('ai_chat_widget_conversation'));

        // 새 페이지(새 컴포넌트 마운트)가 같은 세션에서 열림 상태와 보던 대화를 이어받는다.
        Livewire::test(AiChatWidget::class)
            ->assertSet('isOpen', true)
            ->assertSet('activeConversationId', $conversation->id)
            ->assertSee('새 소식입니다.');
    }

    public function test_someone_elses_conversation_id_in_the_session_shows_nothing(): void
    {
        $this->enableProvider();
        $admin = $this->admin();
        $other = $this->admin('other@test.local');
        $theirs = $this->conversationWithAnswer($other);
        $this->actingAs($admin);

        Livewire::test(AiChatWidget::class)
            ->set('isOpen', true)
            ->set('activeConversationId', $theirs->id)
            ->assertDontSee('새 소식입니다.')
            ->assertSet('activeConversationId', null);
    }

    public function test_assistant_markdown_is_rendered_safely_in_the_widget(): void
    {
        $this->enableProvider();
        $admin = $this->admin();
        $conversation = $this->conversationWithAnswer($admin, "**굵게** [링크](https://example.com) <script>alert(1)</script> ![추적](https://evil.test/p.png?q=secret) [나쁨](javascript:alert(1))");
        $this->actingAs($admin);

        $html = Livewire::test(AiChatWidget::class)
            ->set('isOpen', true)
            ->set('activeConversationId', $conversation->id)
            ->html();

        $this->assertStringContainsString('<strong>굵게</strong>', $html);
        $this->assertStringContainsString('rel="noopener noreferrer nofollow"', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('<img src="https://evil', $html);
        $this->assertStringNotContainsString('evil.test/p.png', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function test_widget_markup_uses_the_external_stylesheet_and_labelled_accessible_controls(): void
    {
        $this->enableProvider();
        $admin = $this->admin();
        $this->actingAs($admin);

        $html = Livewire::test(AiChatWidget::class)->set('isOpen', true)->html();

        // 스타일은 blade 안 <style> 이 아니라 public/css/ai-chat.css(토큰 색)로 분리했다.
        $this->assertStringNotContainsString('<style', $html);
        $this->assertDoesNotMatchRegularExpression('/style="[^"]*(color|background)/i', $html, '인라인 색 지정 금지 - 토큰 CSS 만 쓴다');

        foreach ([
            'role="log"', 'aria-label="AI 비서 대화"', 'role="dialog"', 'aria-labelledby="aic-title"',
            'for="aic-input"', 'aria-describedby="aic-hint aic-usage"', 'aria-label="AI 비서 닫기"',
            'Shift+Enter', 'isComposing', // 한글 조합 중 Enter 로 전송되지 않게
            'x-on:keydown.escape',
        ] as $needle) {
            $this->assertStringContainsString($needle, $html, "위젯 마크업에 '{$needle}' 가 있어야 합니다");
        }

        $this->get('/admin')->assertOk()->assertSee('css/ai-chat.css', false)->assertSee('js/ai-chat.js', false);
    }
}
