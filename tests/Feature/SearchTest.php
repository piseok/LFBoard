<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 2026-10-12: 전체/게시판별 결과 표시를 검증하는 통합검색 테스트 추가.
// 검색어 강조 시 HTML 이스케이프, 빈 검색 안내, 비밀글과 검색 제외·권한 제한 게시판 제외도 확인한다.
class SearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_shows_results_grouped_by_board_by_default(): void
    {
        $this->createResult('notice', '공지사항', '검색 공지');
        $this->createResult('news', '언론보도', '검색 소식');

        $this->get('/search?'.http_build_query(['q' => '검색']))
            ->assertOk()
            ->assertSeeText('총 2건을 찾았습니다.')
            ->assertSeeText('검색 공지')
            ->assertSeeText('검색 소식');
    }

    public function test_board_tab_only_shows_posts_from_the_selected_board(): void
    {
        $this->createResult('notice', '공지사항', '검색 공지');
        $this->createResult('news', '언론보도', '검색 소식');

        $this->get('/search?'.http_build_query(['q' => '검색', 'board' => 'news']))
            ->assertOk()
            ->assertSeeText('검색 소식')
            ->assertDontSeeText('검색 공지');
    }

    public function test_highlighted_results_escape_html_in_keywords_titles_and_excerpts(): void
    {
        $keyword = '<b>&';
        $this->createResult('notice', '공지사항', $keyword.' 검색 결과', '<p>본문 &lt;img src=x onerror=alert(1)&gt;</p>');

        $this->get('/search?'.http_build_query(['q' => $keyword]))
            ->assertOk()
            ->assertSee('<mark class="search-highlight">&lt;b&gt;&amp;</mark>', false)
            ->assertSee('&lt;img src=x onerror=alert(1)&gt;', false)
            ->assertDontSee('<img src=x onerror=alert(1)>', false)
            ->assertDontSee('<b>&', false);
    }

    public function test_empty_search_and_no_matches_show_guidance(): void
    {
        $this->get('/search')->assertOk()->assertSeeText('검색어를 입력해 주세요.');

        $this->get('/search?'.http_build_query(['q' => '없는검색어']))
            ->assertOk()
            ->assertSeeText('없는검색어 검색 결과가 없습니다.');
    }

    public function test_all_results_exclude_private_posts_and_hidden_boards(): void
    {
        $public = $this->createResult('notice', '공지사항', '검색 공개글');
        Post::create([
            'board_id' => $public->board_id, 'title' => '검색 비밀글', 'content' => '내용',
            'is_active' => true, 'is_secret' => true,
        ]);
        $excluded = $this->createResult('excluded', '검색 제외', '검색 제외글');
        $excluded->board->update(['exclude_from_search' => true]);
        $restricted = $this->createResult('restricted', '회원 전용', '검색 회원글');
        $restricted->board->update(['min_read_level' => 2]);

        $this->get('/search?'.http_build_query(['q' => '검색']))
            ->assertOk()
            ->assertSeeText('검색 공개글')
            ->assertDontSeeText('검색 비밀글')
            ->assertDontSeeText('검색 제외글')
            ->assertDontSeeText('검색 회원글');
    }

    private function createResult(string $slug, string $name, string $title, string $content = '검색 결과 본문'): Post
    {
        $board = Board::create([
            'slug' => $slug, 'name' => $name, 'locale' => 'ko', 'is_active' => true,
            'min_read_level' => 0, 'exclude_from_search' => false,
        ]);

        return Post::create([
            'board_id' => $board->id, 'title' => $title, 'content' => $content,
            'author_name' => '작성자', 'is_active' => true, 'is_draft' => false,
        ]);
    }
}
