<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Laravel 기본 에러 뷰(단순 "404 | NOT FOUND" 텍스트만 있는 화면)를 프론트는 사이트
// 헤더/푸터를 포함한 안내 페이지로, 관리자 화면은 별도의 간단한 안내 페이지로 바꿔둔 것의
// 회귀 테스트. abort($code, '구체적인 메시지')로 넘긴 메시지가 그대로 보이는지도 함께 확인한다
// (앱 곳곳에서 "비밀글은 작성자와 관리자만 볼 수 있습니다." 같은 구체적인 안내를 쓰고 있어서
// 이게 사라지면 안 됨).
class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_front_404_uses_site_layout_with_friendly_message(): void
    {
        $response = $this->get('/no-such-page-xyz');

        $response->assertStatus(404);
        $response->assertSee('해당 페이지는 존재하지 않습니다', false);
        $response->assertSee('mobile-menu', false); // partials.header가 렌더된 흔적
    }

    public function test_admin_404_uses_admin_styled_page_not_the_front_layout(): void
    {
        $response = $this->get('/admin/no-such-page-xyz');

        $response->assertStatus(404);
        $response->assertSee('관리자 홈으로', false);
        $response->assertDontSee('mobile-menu', false);
    }

    public function test_abort_with_custom_message_is_shown_instead_of_generic_text(): void
    {
        $member = User::create([
            'name' => 'Member', 'email' => 'member@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_MEMBER, 'is_active' => true,
        ]);

        $board = Board::create([
            'name' => '자유게시판', 'slug' => 'free', 'locale' => 'ko', 'skin' => 'default',
            'is_active' => true, 'allow_comment' => false,
        ]);
        $post = Post::create(['board_id' => $board->id, 'title' => 'x', 'content' => 'x', 'is_active' => true]);

        // CommentController::store()가 allow_comment=false일 때 던지는 구체적인 메시지.
        $response = $this->actingAs($member)->post("/board/free/{$post->id}/comment", ['content' => 'hi']);

        $response->assertStatus(403);
        $response->assertSee('댓글을 사용할 수 없는 게시판입니다', false);
        $response->assertDontSee('이 페이지에 접근할 수 있는 권한이 없습니다', false);
    }

    public function test_plain_abort_without_message_falls_back_to_friendly_default(): void
    {
        $member = User::create([
            'name' => 'Member', 'email' => 'member@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_MEMBER, 'is_active' => true,
        ]);
        $otherMember = User::create([
            'name' => 'Other', 'email' => 'other@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_MEMBER, 'is_active' => true,
        ]);

        // MyPageController가 다른 회원의 정보 수정 화면 접근 시 순수 abort(403)(메시지 없음)을 던진다.
        $response = $this->actingAs($otherMember)->get('/mypage/edit');
        $response->assertSuccessful(); // 본인 접근은 정상.

        // 관리자 레벨이 아닌데 CheckUserLevel이 막는 경로로 순수 abort(403) 케이스를 재현.
        $response = $this->actingAs($member)->get('/admin');
        $response->assertStatus(403);
        $response->assertSee('이 페이지에 접근할 수 있는 권한이 없습니다', false);
    }
}
