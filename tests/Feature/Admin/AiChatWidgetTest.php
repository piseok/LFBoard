<?php

namespace Tests\Feature\Admin;

use App\Livewire\AiChatWidget;
use App\Models\AiChatConversation;
use App\Models\User;
use App\Services\SiteSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

// 관리자 패널 전역 AI 비서 위젯. 권한(ai_assistant)이 없거나 설정된 AI 제공자가 하나도 없으면
// 아예 아무것도 노출하지 않아야 하고, 대화 목록/기록은 항상 로그인한 관리자 본인 것만 보여야
// 한다(슈퍼관리자도 이 위젯 안에서는 예외 없음 — 전체 열람은 별도의 AiChatLogResource에서만).
class AiChatWidgetTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        return User::create([
            'name' => 'Manager', 'email' => 'manager@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'manager', 'admin_permissions' => [], 'is_active' => true,
        ]);
    }

    private function superAdmin(string $email = 'super@test.local'): User
    {
        return User::create([
            'name' => 'Super', 'email' => $email, 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'super', 'is_active' => true,
        ]);
    }

    public function test_widget_has_no_providers_when_admin_lacks_permission_even_with_configured_key(): void
    {
        app(SiteSettingService::class)->set('ai_openai_api_key', 'sk-test', 'ai');
        $manager = $this->manager();

        $this->actingAs($manager);

        Livewire::test(AiChatWidget::class)
            ->assertSet('providers', []);
    }

    public function test_widget_has_no_providers_when_no_api_key_is_configured_even_for_super_admin(): void
    {
        $super = $this->superAdmin();

        $this->actingAs($super);

        Livewire::test(AiChatWidget::class)
            ->assertSet('providers', []);
    }

    public function test_widget_shows_provider_once_permission_and_key_both_present(): void
    {
        app(SiteSettingService::class)->set('ai_openai_api_key', 'sk-test', 'ai');
        $manager = User::create([
            'name' => 'Manager', 'email' => 'm2@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'manager', 'admin_permissions' => ['ai_assistant'], 'is_active' => true,
        ]);

        $this->actingAs($manager);

        $providers = Livewire::test(AiChatWidget::class)->get('providers');
        $this->assertArrayHasKey('openai', $providers);
    }

    public function test_admin_cannot_see_another_admins_conversations_in_the_widget(): void
    {
        app(SiteSettingService::class)->set('ai_openai_api_key', 'sk-test', 'ai');
        $adminA = $this->superAdmin('a@test.local');
        $adminB = $this->superAdmin('b@test.local');

        AiChatConversation::create(['user_id' => $adminA->id, 'provider' => 'openai', 'title' => 'A의 대화']);

        $this->actingAs($adminB);
        $component = Livewire::test(AiChatWidget::class);

        $this->assertCount(0, $component->instance()->conversations());
    }

    public function test_super_admin_only_sees_own_conversations_in_widget_not_all(): void
    {
        app(SiteSettingService::class)->set('ai_openai_api_key', 'sk-test', 'ai');
        $superA = $this->superAdmin('super-a@test.local');
        $superB = $this->superAdmin('super-b@test.local');

        AiChatConversation::create(['user_id' => $superA->id, 'provider' => 'openai', 'title' => 'A의 대화']);
        $ownConversation = AiChatConversation::create(['user_id' => $superB->id, 'provider' => 'openai', 'title' => 'B의 대화']);

        $this->actingAs($superB);
        $conversations = Livewire::test(AiChatWidget::class)->instance()->conversations();

        $this->assertCount(1, $conversations);
        $this->assertSame($ownConversation->id, $conversations->first()->id);
    }

    public function test_deleting_a_conversation_soft_deletes_it_and_it_disappears_from_the_list(): void
    {
        app(SiteSettingService::class)->set('ai_openai_api_key', 'sk-test', 'ai');
        $admin = $this->superAdmin();
        $conversation = AiChatConversation::create(['user_id' => $admin->id, 'provider' => 'openai', 'title' => '지울 대화']);

        $this->actingAs($admin);

        Livewire::test(AiChatWidget::class)
            ->call('deleteConversation', $conversation->id);

        $this->assertSoftDeleted('ai_chat_conversations', ['id' => $conversation->id]);
    }

    public function test_admin_cannot_delete_another_admins_conversation(): void
    {
        app(SiteSettingService::class)->set('ai_openai_api_key', 'sk-test', 'ai');
        $owner = $this->superAdmin('owner@test.local');
        $other = $this->superAdmin('other@test.local');
        $conversation = AiChatConversation::create(['user_id' => $owner->id, 'provider' => 'openai', 'title' => '남의 대화']);

        $this->actingAs($other);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        Livewire::test(AiChatWidget::class)->call('deleteConversation', $conversation->id);
    }
}
