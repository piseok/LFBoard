<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\AiChatLogs\Pages\ListAiChatLogs;
use App\Filament\Widgets\AiUsageTodayWidget;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\AiChatProposal;
use App\Models\AiChatUsage;
use App\Models\User;
use App\Services\SiteSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

// AI 비서 사용 내역(감독 화면) - 오늘 관리자별 사용량(일일 한도 기준)과 읽기 전용 행동 카드·안전한 마크다운.
class AiChatLogResourceV2Test extends TestCase
{
    use RefreshDatabase;

    private function admin(string $email, string $name = 'Admin'): User
    {
        return User::create([
            'name' => $name, 'email' => $email, 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'super', 'is_active' => true,
        ]);
    }

    public function test_the_list_page_shows_the_todays_usage_widget_to_supervisors_only(): void
    {
        app(SiteSettingService::class)->set('ai_openai_api_key', 'sk-test', 'ai');
        $super = $this->admin('super@test.local', '슈퍼');
        $other = $this->admin('other@test.local', '다른관리자');
        AiChatUsage::create(['user_id' => $other->id, 'provider' => 'openai', 'kind' => 'chat', 'created_at' => now()]);
        AiChatUsage::create(['user_id' => $other->id, 'provider' => 'openai', 'kind' => 'chat', 'created_at' => now()]);
        AiChatUsage::create(['user_id' => $other->id, 'provider' => 'openai', 'kind' => 'image', 'created_at' => now()]);

        $this->actingAs($super);
        Livewire::test(ListAiChatLogs::class)->assertSeeLivewire(AiUsageTodayWidget::class);
        Livewire::test(AiUsageTodayWidget::class)
            ->assertSee('오늘 AI 사용량')
            ->assertSee('우리 사이트의 일일 한도 기준')
            ->assertSee('다른관리자')
            ->assertSeeText('사이트 전체 오늘 질문 2/150회')
            ->assertSeeText('이미지 1/20장');

        // 일반 관리자는 위젯도 볼 수 없다.
        $manager = User::create([
            'name' => 'Manager', 'email' => 'manager@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'manager', 'admin_permissions' => ['ai_assistant'], 'is_active' => true,
        ]);
        $this->actingAs($manager);
        $this->assertFalse(AiUsageTodayWidget::canView());
    }

    public function test_the_log_view_renders_cards_read_only_and_markdown_safely(): void
    {
        $owner = $this->admin('owner@test.local');
        $conversation = AiChatConversation::create(['user_id' => $owner->id, 'provider' => 'gemini', 'title' => '대화']);
        AiChatMessage::create(['conversation_id' => $conversation->id, 'role' => 'user', 'content' => '팝업 만들어줘']);
        $answer = AiChatMessage::create([
            'conversation_id' => $conversation->id, 'role' => 'assistant',
            'content' => '**준비했어요** <script>alert(1)</script> ![추적](https://evil.test/t.png?x=1)',
        ]);
        AiChatProposal::create([
            'message_id' => $answer->id, 'kind' => 'popup', 'status' => 'proposed', 'created_at' => now(), 'expires_at' => now()->addHour(),
            'payload' => ['title' => '여름 이벤트', 'body' => '본문', 'content_type' => 'html', 'started_at' => '2026-10-21 00:00:00', 'ended_at' => null, 'image_prompt' => null],
        ]);

        $html = view('filament.resources.ai-chat-logs.messages', [
            'messages' => $conversation->messages()->with('proposals')->orderBy('id')->get(),
        ])->render();

        $this->assertStringContainsString('<strong>준비했어요</strong>', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('<img src="https://evil', $html);
        $this->assertStringNotContainsString('evil.test/t.png', $html);

        // 카드는 내용·상태만 보이고 버튼(링크·wire:click)이 없다.
        $this->assertStringContainsString('팝업 초안', $html);
        $this->assertStringContainsString('여름 이벤트', $html);
        $this->assertStringContainsString('읽기 전용', $html);
        $this->assertStringNotContainsString('ai_proposal=', $html);
        $this->assertStringNotContainsString('wire:click', $html);
        $this->assertStringNotContainsString('작성 화면 열기', $html);
    }
}
