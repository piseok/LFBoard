<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Inquiries\Pages\EditInquiry;
use App\Filament\Resources\Popups\Pages\CreatePopup;
use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Models\Board;
use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

// AI 비서 설계의 핵심 안전장치: AI가 만든 내용은 열려 있는 작성 폼에 "채워주기"만 하고,
// 저장은 반드시 관리자가 화면의 기존 저장 버튼을 직접 눌러야만 일어난다. fill-form-field
// 이벤트 리스너(HasAiFormFill) 자체에는 저장 로직이 전혀 없어야 한다 — 이 테스트가 깨지면
// AI가 사람 개입 없이 콘텐츠를 직접 저장할 수 있게 된 것이므로 반드시 막아야 하는 회귀다.
class AiFormFillSafetyTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin', 'email' => 'admin@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'super', 'is_active' => true,
        ]);
    }

    public function test_filling_a_post_form_field_does_not_create_a_post(): void
    {
        $board = Board::create(['name' => 'B', 'slug' => 'b', 'locale' => 'ko', 'skin' => 'default', 'is_active' => true, 'use_editor' => false]);
        $this->actingAs($this->admin());

        Livewire::test(CreatePost::class)
            ->call('fillAiGeneratedField', 'title', 'AI가 생성한 제목')
            ->assertSet('data.title', 'AI가 생성한 제목');

        $this->assertDatabaseCount('posts', 0);
    }

    public function test_filling_a_popup_image_field_does_not_create_a_popup(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreatePopup::class)
            ->call('fillAiGeneratedField', 'image_path', 'uploads/ai_generated/2026/07/fake.png')
            ->assertSet('data.image_path', 'uploads/ai_generated/2026/07/fake.png');

        $this->assertDatabaseCount('popups', 0);
    }

    public function test_filling_an_inquiry_reply_does_not_persist_until_explicit_save(): void
    {
        $inquiry = Inquiry::create([
            'name' => '문의자', 'email' => 'q@test.local', 'title' => '문의', 'content' => '문의 내용',
            'locale' => 'ko',
        ]);
        $this->actingAs($this->admin());

        Livewire::test(EditInquiry::class, ['record' => $inquiry->getRouteKey()])
            ->call('fillAiGeneratedField', 'admin_reply', 'AI가 초안으로 작성한 답변')
            ->assertSet('data.admin_reply', 'AI가 초안으로 작성한 답변');

        $this->assertDatabaseHas('inquiries', ['id' => $inquiry->id, 'admin_reply' => null]);
    }

    public function test_normal_save_after_fill_still_works_as_the_only_write_path(): void
    {
        $board = Board::create(['name' => 'B', 'slug' => 'b', 'locale' => 'ko', 'skin' => 'default', 'is_active' => true, 'use_editor' => false]);
        $this->actingAs($this->admin());

        Livewire::test(CreatePost::class)
            ->call('fillAiGeneratedField', 'title', 'AI가 생성한 제목')
            ->fillForm(['board_id' => $board->id, 'content_text' => '본문'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('posts', ['title' => 'AI가 생성한 제목']);
    }
}
