<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

// <x-latest-posts board="slug" :limit="n" /> — 어디서든(메인 페이지 등) 특정 게시판의 최신글을
// 가져다 쓸 수 있는 재사용 컴포넌트. 스킨은 board.skins.{skin}과 동일한 방식으로 교체 가능.
class LatestPostsComponentTest extends TestCase
{
    use RefreshDatabase;

    private function board(string $slug = 'notice'): Board
    {
        return Board::create([
            'name' => '공지사항', 'slug' => $slug, 'locale' => 'ko', 'skin' => 'default', 'is_active' => true,
        ]);
    }

    public function test_renders_latest_posts_for_the_given_board_newest_first(): void
    {
        $board = $this->board();
        $old = Post::create(['board_id' => $board->id, 'title' => '오래된 글', 'content' => 'x', 'is_active' => true, 'created_at' => now()->subDays(2)]);
        $new = Post::create(['board_id' => $board->id, 'title' => '새 글', 'content' => 'x', 'is_active' => true, 'created_at' => now()]);

        $html = Blade::render('<x-latest-posts board="notice" :limit="5" />');

        $this->assertStringContainsString('공지사항', $html);
        $this->assertStringContainsString('새 글', $html);
        $this->assertStringContainsString('오래된 글', $html);
        $this->assertTrue(strpos($html, '새 글') < strpos($html, '오래된 글'));
    }

    public function test_respects_the_limit_prop(): void
    {
        $board = $this->board();
        foreach (range(1, 6) as $i) {
            Post::create(['board_id' => $board->id, 'title' => "글 {$i}", 'content' => 'x', 'is_active' => true]);
        }

        $html = Blade::render('<x-latest-posts board="notice" :limit="3" />');

        $this->assertSame(3, substr_count($html, 'latest-posts-item-title'));
    }

    public function test_excludes_inactive_draft_and_secret_posts(): void
    {
        $board = $this->board();
        Post::create(['board_id' => $board->id, 'title' => '비활성 글', 'content' => 'x', 'is_active' => false]);
        Post::create(['board_id' => $board->id, 'title' => '임시저장 글', 'content' => 'x', 'is_active' => true, 'is_draft' => true]);
        Post::create(['board_id' => $board->id, 'title' => '비밀 글', 'content' => 'x', 'is_active' => true, 'is_secret' => true]);
        Post::create(['board_id' => $board->id, 'title' => '공개 글', 'content' => 'x', 'is_active' => true]);

        $html = Blade::render('<x-latest-posts board="notice" :limit="5" />');

        $this->assertStringContainsString('공개 글', $html);
        $this->assertStringNotContainsString('비활성 글', $html);
        $this->assertStringNotContainsString('임시저장 글', $html);
        $this->assertStringNotContainsString('비밀 글', $html);
    }

    public function test_renders_nothing_when_board_does_not_exist(): void
    {
        $html = Blade::render('<x-latest-posts board="no-such-board" :limit="5" />');

        $this->assertSame('', trim($html));
    }

    public function test_shows_empty_message_when_board_has_no_posts(): void
    {
        $this->board();

        $html = Blade::render('<x-latest-posts board="notice" :limit="5" />');

        $this->assertStringContainsString('등록된 게시글이 없습니다', $html);
    }

    public function test_falls_back_to_list_skin_when_requested_skin_is_missing(): void
    {
        $board = $this->board();
        Post::create(['board_id' => $board->id, 'title' => '글', 'content' => 'x', 'is_active' => true]);

        $html = Blade::render('<x-latest-posts board="notice" :limit="5" skin="no-such-skin" />');

        $this->assertStringContainsString('latest-posts-list', $html);
    }

    public function test_card_skin_renders_a_truncated_excerpt_with_html_stripped(): void
    {
        $board = $this->board();
        Post::create([
            'board_id' => $board->id, 'title' => '글', 'is_active' => true,
            'content' => '<p>'.str_repeat('가', 100).'</p>',
        ]);

        $html = Blade::render('<x-latest-posts board="notice" :limit="5" skin="card" />');

        $this->assertStringContainsString('latest-posts-card-grid', $html);
        $this->assertStringNotContainsString('<p>', $html);
        $this->assertStringContainsString('...', $html);
    }
}
