<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Boards\Pages\CreateBoard;
use App\Filament\Resources\Boards\Pages\ListBoards;
use App\Models\Board;
use App\Models\Language;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

// 커스텀 필드(custom_field_schema) Repeater가 ->default([]) 없이 정의돼 있으면 관리자가 그
// 탭을 한 번도 건드리지 않아도 Filament가 빈 항목 하나를 몰래 채워 넣고, 그 항목의 필드
// 키/표시명이 필수라 게시판 생성 자체가 막히는 버그가 있었다 — 커스텀 필드가 필요 없는
// 대다수 게시판에서 매번 걸리는 회귀 테스트.
class BoardResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_board_can_be_created_without_touching_the_custom_fields_tab(): void
    {
        Language::create(['code' => 'ko', 'name' => '한국어', 'is_active' => true, 'sort_order' => 1]);

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'super', 'is_active' => true,
        ]);

        $this->actingAs($admin);

        Livewire::test(CreateBoard::class)
            ->fillForm(['name' => '테스트게시판', 'slug' => 'test-board', 'locale' => 'ko'])
            ->call('create')
            ->assertHasNoFormErrors();

        $board = Board::where('slug', 'test-board')->firstOrFail();
        $this->assertSame([], $board->custom_field_schema);
    }

    // 게시판 목록의 "글 수"(posts_count) 컬럼 — PostResource 목록·대시보드 위젯과 동일하게
    // Post::scopeVisibleTo() 규칙을 따라야 한다: 임시저장 글은 작성한 본인만 볼 수 있으므로,
    // 다른 사용자의 임시저장 글은 이 숫자에도 포함되면 안 된다(슈퍼관리자가 봐도 마찬가지).
    public function test_posts_count_column_excludes_other_users_draft_posts(): void
    {
        $admin = User::create([
            'name' => 'Super', 'email' => 'super@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'super', 'is_active' => true,
        ]);
        $otherUser = User::create([
            'name' => 'Writer', 'email' => 'writer@test.local', 'password' => bcrypt('password'),
            'level' => 1, 'is_active' => true,
        ]);

        $board = Board::create([
            'name' => '공지사항', 'slug' => 'notice', 'locale' => 'ko', 'skin' => 'default',
            'layout' => 'list', 'is_active' => true,
        ]);

        Post::create([
            'board_id' => $board->id, 'user_id' => $otherUser->id, 'title' => '발행글1',
            'content' => '내용', 'is_draft' => false, 'is_active' => true,
        ]);
        Post::create([
            'board_id' => $board->id, 'user_id' => $otherUser->id, 'title' => '발행글2',
            'content' => '내용', 'is_draft' => false, 'is_active' => true,
        ]);
        // 다른 사용자(otherUser)의 임시저장 글 — admin에게는 보이면 안 된다.
        Post::create([
            'board_id' => $board->id, 'user_id' => $otherUser->id, 'title' => '임시저장글',
            'content' => '내용', 'is_draft' => true, 'is_active' => true,
        ]);

        Livewire::actingAs($admin)->test(ListBoards::class)
            ->assertTableColumnStateSet('posts_count', 2, $board);
    }
}
