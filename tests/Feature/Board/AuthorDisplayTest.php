<?php

namespace Tests\Feature\Board;

use App\Models\Board;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 2026-07-05에 실제로 있었던 버그의 회귀 테스트: 닉네임 필드가 꺼져 있거나(사이트 설정에서
// 선택/숨김 처리 가능) 회원이 그냥 닉네임을 안 정한 경우, 게시글/댓글 작성자 표시가
// "$user->nickname ?? $author_name ?? '비회원'"처럼 회원의 실제 이름(name)을 건너뛰어서
// 로그인한 회원 본인 글까지 전부 "비회원"으로 잘못 표시되던 문제가 있었다.
class AuthorDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_shows_members_name_when_nickname_is_not_set(): void
    {
        $member = User::create([
            'name' => '홍길동', 'email' => 'member@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_MEMBER, 'is_active' => true, 'nickname' => null,
        ]);

        $board = Board::create(['name' => '자유게시판', 'slug' => 'free', 'locale' => 'ko', 'skin' => 'default', 'is_active' => true]);
        $post = Post::create(['board_id' => $board->id, 'user_id' => $member->id, 'title' => '제목', 'content' => '내용', 'is_active' => true]);

        $response = $this->get("/board/free/{$post->id}");

        $response->assertSuccessful();
        $response->assertSee('홍길동');
        $response->assertDontSee('비회원');
    }

    public function test_post_list_shows_members_name_when_nickname_is_not_set(): void
    {
        $member = User::create([
            'name' => '김철수', 'email' => 'member2@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_MEMBER, 'is_active' => true, 'nickname' => null,
        ]);

        $board = Board::create(['name' => '자유게시판', 'slug' => 'free', 'locale' => 'ko', 'skin' => 'default', 'is_active' => true]);
        Post::create(['board_id' => $board->id, 'user_id' => $member->id, 'title' => '목록 테스트', 'content' => '내용', 'is_active' => true]);

        $response = $this->get('/board/free');

        $response->assertSuccessful();
        $response->assertSee('김철수');
    }

    public function test_post_shows_nickname_when_member_has_one(): void
    {
        $member = User::create([
            'name' => '이영희', 'email' => 'member3@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_MEMBER, 'is_active' => true, 'nickname' => '별명이에요',
        ]);

        $board = Board::create(['name' => '자유게시판', 'slug' => 'free', 'locale' => 'ko', 'skin' => 'default', 'is_active' => true]);
        $post = Post::create(['board_id' => $board->id, 'user_id' => $member->id, 'title' => '제목', 'content' => '내용', 'is_active' => true]);

        // 닉네임이 있으면 닉네임이 우선한다(이름 대신 표시).
        $this->get("/board/free/{$post->id}")->assertSee('별명이에요');
    }
}
