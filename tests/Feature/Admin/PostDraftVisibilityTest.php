<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Filament\Resources\Posts\PostResource;
use App\Models\Board;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

// 임시저장 글은 작성한 본인만 볼 수 있다 — 관리자 화면(PostResource)에서도 다른 관리자는 물론
// 회원의 임시저장 글도 전혀 보이면 안 된다(슈퍼관리자도 예외 없음).
class PostDraftVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_draft_is_invisible_to_every_admin_in_the_resource_query(): void
    {
        $board = Board::create(['name' => 'B', 'slug' => 'b', 'locale' => 'ko', 'skin' => 'default', 'is_active' => true]);
        $member = User::create([
            'name' => 'Member', 'email' => 'member@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_MEMBER, 'is_active' => true,
        ]);
        $superAdmin = User::create([
            'name' => 'Super', 'email' => 'super@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'super', 'is_active' => true,
        ]);
        $draft = Post::create(['board_id' => $board->id, 'user_id' => $member->id, 'title' => '회원 초안', 'content' => '', 'is_draft' => true, 'is_active' => true]);
        $published = Post::create(['board_id' => $board->id, 'user_id' => $member->id, 'title' => '회원 정식글', 'content' => 'x', 'is_draft' => false, 'is_active' => true]);

        $this->actingAs($superAdmin);
        $visibleIds = PostResource::getEloquentQuery()->pluck('id')->all();

        $this->assertNotContains($draft->id, $visibleIds, '회원의 임시저장 글은 슈퍼관리자에게도 보이면 안 된다');
        $this->assertContains($published->id, $visibleIds);
    }

    public function test_each_admins_own_draft_is_hidden_from_other_admins(): void
    {
        $board = Board::create(['name' => 'B', 'slug' => 'b', 'locale' => 'ko', 'skin' => 'default', 'is_active' => true]);
        $adminA = User::create([
            'name' => 'AdminA', 'email' => 'admin-a@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'super', 'is_active' => true,
        ]);
        $adminB = User::create([
            'name' => 'AdminB', 'email' => 'admin-b@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'super', 'is_active' => true,
        ]);
        $draftByA = Post::create(['board_id' => $board->id, 'user_id' => $adminA->id, 'title' => 'A의 초안', 'content' => '', 'is_draft' => true, 'is_active' => true]);

        $this->actingAs($adminA);
        $this->assertContains($draftByA->id, PostResource::getEloquentQuery()->pluck('id')->all(), '작성한 본인은 자기 임시저장을 볼 수 있어야 한다');

        $this->actingAs($adminB);
        $this->assertNotContains($draftByA->id, PostResource::getEloquentQuery()->pluck('id')->all(), '다른 관리자의 임시저장은 슈퍼관리자여도 보이면 안 된다');
    }

    public function test_creating_a_draft_via_admin_panel_assigns_ownership_to_the_saving_admin(): void
    {
        $board = Board::create(['name' => 'B', 'slug' => 'b', 'locale' => 'ko', 'skin' => 'default', 'is_active' => true, 'use_editor' => false]);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'super', 'is_active' => true,
        ]);

        $this->actingAs($admin);

        Livewire::test(CreatePost::class)
            ->fillForm([
                'board_id' => $board->id,
                'title' => '관리자 초안',
                'content_text' => '아직 쓰는 중',
                'is_draft' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('posts', [
            'title' => '관리자 초안', 'is_draft' => true, 'user_id' => $admin->id,
        ]);
    }

    // user_id를 임시저장일 때만 지정하던 버그 — 일반 발행 글은 user_id가 계속 null로 남아
    // 목록/상세의 "작성자"가 항상 "비회원"으로 잘못 표시됐다(실사용자 발견).
    public function test_creating_a_published_post_via_admin_panel_assigns_ownership_to_the_saving_admin(): void
    {
        $board = Board::create(['name' => 'B', 'slug' => 'b', 'locale' => 'ko', 'skin' => 'default', 'is_active' => true, 'use_editor' => false]);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'super', 'is_active' => true,
        ]);

        $this->actingAs($admin);

        Livewire::test(CreatePost::class)
            ->fillForm([
                'board_id' => $board->id,
                'title' => '관리자 정식글',
                'content_text' => '발행된 글',
                'is_draft' => false,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('posts', [
            'title' => '관리자 정식글', 'is_draft' => false, 'user_id' => $admin->id,
        ]);
    }
}
