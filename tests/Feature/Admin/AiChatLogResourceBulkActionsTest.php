<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\AiChatLogs\Pages\ListAiChatLogs;
use App\Models\AiChatConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

// AiChatLogResource의 삭제/복구 액션이 실제 Filament 테이블 액션으로도 정상 동작하는지
// 확인한다(AiChatLogResourceTest는 모델/쿼리 레벨만 확인했음).
class AiChatLogResourceBulkActionsTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(string $email = 'super@test.local'): User
    {
        return User::create([
            'name' => 'Super', 'email' => $email, 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'super', 'is_active' => true,
        ]);
    }

    public function test_super_admin_can_bulk_soft_delete_conversations(): void
    {
        $super = $this->superAdmin();
        $owner = $this->superAdmin('owner@test.local');
        $conversation = AiChatConversation::create(['user_id' => $owner->id, 'provider' => 'openai', 'title' => '대상']);

        $this->actingAs($super);

        Livewire::test(ListAiChatLogs::class)
            ->callTableBulkAction('delete', [$conversation]);

        $this->assertSoftDeleted('ai_chat_conversations', ['id' => $conversation->id]);
    }

    public function test_super_admin_can_restore_a_soft_deleted_conversation(): void
    {
        $super = $this->superAdmin();
        $owner = $this->superAdmin('owner2@test.local');
        $conversation = AiChatConversation::create(['user_id' => $owner->id, 'provider' => 'gemini', 'title' => '대상']);
        $conversation->delete();

        $this->actingAs($super);

        Livewire::test(ListAiChatLogs::class)
            ->filterTable('trashed')
            ->callTableBulkAction('restore', [$conversation]);

        $this->assertDatabaseHas('ai_chat_conversations', ['id' => $conversation->id, 'deleted_at' => null]);
    }

    public function test_super_admin_can_force_delete_a_conversation(): void
    {
        $super = $this->superAdmin();
        $owner = $this->superAdmin('owner3@test.local');
        $conversation = AiChatConversation::create(['user_id' => $owner->id, 'provider' => 'openai', 'title' => '영구삭제']);
        $conversation->delete();

        $this->actingAs($super);

        Livewire::test(ListAiChatLogs::class)
            ->filterTable('trashed')
            ->callTableBulkAction('forceDelete', [$conversation]);

        $this->assertDatabaseMissing('ai_chat_conversations', ['id' => $conversation->id]);
    }
}
