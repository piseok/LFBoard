<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\AiChatLogs\AiChatLogResource;
use App\Models\AiChatConversation;
use App\Models\User;
use App\Services\SiteSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 사용자가 명시적으로 요청한 예외: 일반 대화 프라이버시(위젯에서는 자기 것만)와 달리, 여기는
// 슈퍼관리자가 전체 관리자의 AI 사용 내역을 감독/삭제할 수 있는 화면이어야 한다.
class AiChatLogResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_cannot_access_the_resource(): void
    {
        $manager = User::create([
            'name' => 'Manager', 'email' => 'manager@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'manager', 'is_active' => true,
        ]);

        $this->actingAs($manager);

        $this->assertFalse(AiChatLogResource::canAccess());
    }

    public function test_super_admin_can_see_every_admins_conversations(): void
    {
        $superViewer = User::create([
            'name' => 'Super', 'email' => 'super@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'super', 'is_active' => true,
        ]);
        $otherAdmin = User::create([
            'name' => 'Other', 'email' => 'other@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'super', 'is_active' => true,
        ]);
        $conversation = AiChatConversation::create(['user_id' => $otherAdmin->id, 'provider' => 'openai', 'title' => '다른 관리자 대화']);

        $this->actingAs($superViewer);
        $this->assertTrue(AiChatLogResource::canAccess());

        $visibleIds = AiChatLogResource::getEloquentQuery()->pluck('id')->all();
        $this->assertContains($conversation->id, $visibleIds);
    }

    public function test_super_admin_can_soft_delete_another_admins_conversation(): void
    {
        $superViewer = User::create([
            'name' => 'Super', 'email' => 'super@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'super', 'is_active' => true,
        ]);
        $otherAdmin = User::create([
            'name' => 'Other', 'email' => 'other@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'super', 'is_active' => true,
        ]);
        $conversation = AiChatConversation::create(['user_id' => $otherAdmin->id, 'provider' => 'openai', 'title' => '삭제 대상']);

        $this->actingAs($superViewer);

        $conversation->delete();

        $this->assertSoftDeleted('ai_chat_conversations', ['id' => $conversation->id]);
    }

    // AiChatWidget과 동일한 규칙 — AI 제공자 API 키가 하나도 설정돼 있지 않으면 메뉴에서도
    // 감춘다(canAccess는 그대로 둬서 예전 기록이 있는 경우 직접 URL로는 계속 볼 수 있음).
    public function test_navigation_is_hidden_when_no_ai_provider_is_configured(): void
    {
        $superAdmin = User::create([
            'name' => 'Super', 'email' => 'super@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'super', 'is_active' => true,
        ]);

        $this->actingAs($superAdmin);

        $this->assertFalse(AiChatLogResource::shouldRegisterNavigation());
        $this->assertTrue(AiChatLogResource::canAccess());
    }

    public function test_navigation_is_shown_once_an_ai_provider_is_configured(): void
    {
        $superAdmin = User::create([
            'name' => 'Super', 'email' => 'super@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'super', 'is_active' => true,
        ]);
        app(SiteSettingService::class)->set('ai_openai_api_key', 'sk-test', 'ai');

        $this->actingAs($superAdmin);

        $this->assertTrue(AiChatLogResource::shouldRegisterNavigation());
    }
}
