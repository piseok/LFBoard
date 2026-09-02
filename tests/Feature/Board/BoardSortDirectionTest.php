<?php

namespace Tests\Feature\Board;

use App\Models\Board;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// order_direction(정렬 방향)이 실제 목록 순서에 반영되는지 확인한다.
// order_by가 기본값(latest=created_at)일 때 asc/desc 각각 오래된 글/최신 글이 먼저 나와야 한다.
class BoardSortDirectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_direction_asc_shows_oldest_post_first(): void
    {
        $board = Board::create([
            'name' => '정렬 테스트', 'slug' => 'sort-test', 'locale' => 'ko', 'skin' => 'default',
            'is_active' => true, 'order_by' => 'latest', 'order_direction' => 'asc',
        ]);

        Post::create([
            'board_id' => $board->id, 'title' => '오래된 글', 'content' => 'old',
            'is_active' => true, 'is_draft' => false, 'created_at' => now()->subDays(2),
        ]);
        Post::create([
            'board_id' => $board->id, 'title' => '최신 글', 'content' => 'new',
            'is_active' => true, 'is_draft' => false, 'created_at' => now(),
        ]);

        $this->get("/board/{$board->slug}")
            ->assertSuccessful()
            ->assertSeeInOrder(['오래된 글', '최신 글']);
    }

    public function test_order_direction_desc_shows_newest_post_first(): void
    {
        $board = Board::create([
            'name' => '정렬 테스트2', 'slug' => 'sort-test-2', 'locale' => 'ko', 'skin' => 'default',
            'is_active' => true, 'order_by' => 'latest', 'order_direction' => 'desc',
        ]);

        Post::create([
            'board_id' => $board->id, 'title' => '오래된 글', 'content' => 'old',
            'is_active' => true, 'is_draft' => false, 'created_at' => now()->subDays(2),
        ]);
        Post::create([
            'board_id' => $board->id, 'title' => '최신 글', 'content' => 'new',
            'is_active' => true, 'is_draft' => false, 'created_at' => now(),
        ]);

        $this->get("/board/{$board->slug}")
            ->assertSuccessful()
            ->assertSeeInOrder(['최신 글', '오래된 글']);
    }
}
