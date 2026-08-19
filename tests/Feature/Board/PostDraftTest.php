<?php

namespace Tests\Feature\Board;

use App\Models\Board;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 게시글 임시저장 기능의 회귀 테스트. 비회원은 계정이 없어 나중에 못 찾아오므로 임시저장을 쓸 수
// 없고, 임시저장은 작성자 본인만 볼 수 있다(관리자도 예외 없음 — canModify()의 관리자 우회와
// 다르게 abortIfHiddenDraft()가 먼저 걸린다).
class PostDraftTest extends TestCase
{
    use RefreshDatabase;

    private function board(): Board
    {
        return Board::create(['name' => '자유게시판', 'slug' => 'free', 'locale' => 'ko', 'skin' => 'default', 'is_active' => true]);
    }

    public function test_member_can_save_a_draft_without_content(): void
    {
        $board = $this->board();
        $member = User::create([
            'name' => 'Member', 'email' => 'member@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_MEMBER, 'is_active' => true,
        ]);

        $response = $this->actingAs($member)->post("/board/{$board->slug}/write", [
            'title' => '제목만 있는 초안', 'save_as_draft' => '1',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('posts', [
            'title' => '제목만 있는 초안', 'user_id' => $member->id, 'is_draft' => true,
        ]);
    }

    public function test_guest_cannot_create_a_draft_even_if_the_field_is_forged(): void
    {
        $board = Board::create(['name' => '자유게시판', 'slug' => 'free', 'locale' => 'ko', 'skin' => 'default', 'is_active' => true, 'allow_anonymous' => true]);

        $this->post("/board/{$board->slug}/write", [
            'title' => '비회원 초안 시도', 'content' => '내용', 'save_as_draft' => '1',
            'author_name' => '홍길동', 'author_password' => 'pass1234',
        ]);

        $post = Post::where('title', '비회원 초안 시도')->first();
        $this->assertNotNull($post);
        $this->assertFalse((bool) $post->is_draft, '비회원은 save_as_draft를 조작해도 임시저장으로 남으면 안 된다');
    }

    public function test_draft_does_not_appear_in_board_index(): void
    {
        $board = $this->board();
        $member = User::create([
            'name' => 'Member', 'email' => 'member@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_MEMBER, 'is_active' => true,
        ]);
        Post::create(['board_id' => $board->id, 'user_id' => $member->id, 'title' => '초안', 'content' => '', 'is_draft' => true, 'is_active' => true]);
        Post::create(['board_id' => $board->id, 'user_id' => $member->id, 'title' => '정식글', 'content' => '내용', 'is_draft' => false, 'is_active' => true]);

        $response = $this->actingAs($member)->get("/board/{$board->slug}");

        $response->assertSee('정식글');
        $response->assertDontSee('초안');
    }

    public function test_draft_is_hidden_from_show_even_for_admin(): void
    {
        $board = $this->board();
        $member = User::create([
            'name' => 'Member', 'email' => 'member@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_MEMBER, 'is_active' => true,
        ]);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'super', 'is_active' => true,
        ]);
        $draft = Post::create(['board_id' => $board->id, 'user_id' => $member->id, 'title' => '초안', 'content' => '', 'is_draft' => true, 'is_active' => true]);

        $this->actingAs($member)->get("/board/{$board->slug}/{$draft->id}")->assertSuccessful();
        $this->actingAs($admin)->get("/board/{$board->slug}/{$draft->id}")->assertNotFound();

        $otherMember = User::create([
            'name' => 'Other', 'email' => 'other@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_MEMBER, 'is_active' => true,
        ]);
        $this->actingAs($otherMember)->get("/board/{$board->slug}/{$draft->id}")->assertNotFound();
    }

    public function test_draft_edit_and_delete_are_hidden_from_admin_too(): void
    {
        $board = $this->board();
        $member = User::create([
            'name' => 'Member', 'email' => 'member@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_MEMBER, 'is_active' => true,
        ]);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_ADMIN, 'admin_role' => 'super', 'is_active' => true,
        ]);
        $draft = Post::create(['board_id' => $board->id, 'user_id' => $member->id, 'title' => '초안', 'content' => '', 'is_draft' => true, 'is_active' => true]);

        $this->actingAs($admin)->get("/board/{$board->slug}/{$draft->id}/edit")->assertNotFound();
        $this->actingAs($admin)->delete("/board/{$board->slug}/{$draft->id}")->assertNotFound();
        $this->assertDatabaseHas('posts', ['id' => $draft->id]);
    }

    public function test_publishing_a_draft_makes_it_visible_and_removes_draft_flag(): void
    {
        $board = $this->board();
        $member = User::create([
            'name' => 'Member', 'email' => 'member@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_MEMBER, 'is_active' => true,
        ]);
        $draft = Post::create(['board_id' => $board->id, 'user_id' => $member->id, 'title' => '초안', 'content' => '', 'is_draft' => true, 'is_active' => true]);

        $this->actingAs($member)->put("/board/{$board->slug}/{$draft->id}", [
            'title' => '정식 게시', 'content' => '이제 다 썼다',
        ])->assertRedirect("/board/{$board->slug}/{$draft->id}");

        $draft->refresh();
        $this->assertFalse((bool) $draft->is_draft);
        $this->actingAs($member)->get("/board/{$board->slug}")->assertSee('정식 게시');
    }

    public function test_cancelling_a_draft_deletes_it(): void
    {
        $board = $this->board();
        $member = User::create([
            'name' => 'Member', 'email' => 'member@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_MEMBER, 'is_active' => true,
        ]);
        $draft = Post::create(['board_id' => $board->id, 'user_id' => $member->id, 'title' => '초안', 'content' => '', 'is_draft' => true, 'is_active' => true]);

        $this->actingAs($member)->delete("/board/{$board->slug}/{$draft->id}")->assertRedirect();

        $this->assertSoftDeleted('posts', ['id' => $draft->id]);
    }

    public function test_draft_does_not_appear_in_site_search(): void
    {
        $board = $this->board();
        $member = User::create([
            'name' => 'Member', 'email' => 'member@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_MEMBER, 'is_active' => true,
        ]);
        Post::create(['board_id' => $board->id, 'user_id' => $member->id, 'title' => '검색용 초안', 'content' => '', 'is_draft' => true, 'is_active' => true]);
        Post::create(['board_id' => $board->id, 'user_id' => $member->id, 'title' => '검색용 정식글', 'content' => '내용', 'is_draft' => false, 'is_active' => true]);

        $response = $this->actingAs($member)->get('/search?q='.urlencode('검색용'));

        $response->assertSee('검색용 정식글');
        $response->assertDontSee('검색용 초안');
    }

    public function test_draft_does_not_appear_in_sitemap(): void
    {
        \App\Models\Language::create(['code' => 'ko', 'name' => '한국어', 'is_default' => true, 'is_active' => true, 'sort_order' => 1]);
        app(\App\Services\SiteSettingService::class)->set('sitemap_enabled', '1');

        $board = $this->board();
        $member = User::create([
            'name' => 'Member', 'email' => 'member@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_MEMBER, 'is_active' => true,
        ]);
        $draft = Post::create(['board_id' => $board->id, 'user_id' => $member->id, 'title' => '초안', 'content' => '', 'is_draft' => true, 'is_active' => true]);
        $published = Post::create(['board_id' => $board->id, 'user_id' => $member->id, 'title' => '정식글', 'content' => '내용', 'is_draft' => false, 'is_active' => true]);

        $xml = $this->get('/sitemap-ko.xml')->getContent();

        $this->assertStringNotContainsString("/{$board->slug}/{$draft->id}", (string) $xml);
        $this->assertStringContainsString("/{$board->slug}/{$published->id}", (string) $xml);
    }

    public function test_commenting_on_a_draft_is_blocked(): void
    {
        $board = Board::create(['name' => '자유게시판', 'slug' => 'free', 'locale' => 'ko', 'skin' => 'default', 'is_active' => true, 'allow_comment' => true]);
        $member = User::create([
            'name' => 'Member', 'email' => 'member@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_MEMBER, 'is_active' => true,
        ]);
        $draft = Post::create(['board_id' => $board->id, 'user_id' => $member->id, 'title' => '초안', 'content' => '', 'is_draft' => true, 'is_active' => true]);

        $this->actingAs($member)
            ->post("/board/{$board->slug}/{$draft->id}/comment", ['content' => '댓글 시도'])
            ->assertNotFound();
    }

    public function test_write_page_offers_to_load_only_the_members_own_drafts(): void
    {
        $board = $this->board();
        $member = User::create([
            'name' => 'Member', 'email' => 'member@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_MEMBER, 'is_active' => true,
        ]);
        $other = User::create([
            'name' => 'Other', 'email' => 'other@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_MEMBER, 'is_active' => true,
        ]);
        Post::create(['board_id' => $board->id, 'user_id' => $member->id, 'title' => '내 초안', 'content' => '', 'is_draft' => true, 'is_active' => true]);
        Post::create(['board_id' => $board->id, 'user_id' => $other->id, 'title' => '남의 초안', 'content' => '', 'is_draft' => true, 'is_active' => true]);

        $response = $this->actingAs($member)->get("/board/{$board->slug}/write");

        $response->assertSee('내 초안');
        $response->assertDontSee('남의 초안');
    }
}
