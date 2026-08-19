<?php

namespace Tests\Feature;

use App\Http\Controllers\CommentController;
use App\Models\Board;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

// 2026-07-05에 실제로 있었던 버그의 회귀 테스트: 같은 slug를 언어별로 재사용하는 게시판(이 앱이
// 의도적으로 지원하는 패턴 — README 12-4 참고)에서, CommentController::store()가 게시판을
// locale 조건 없이 slug만으로 찾아서 엉뚱한 언어의 게시판을 집어버리는 바람에 댓글 작성이 항상
// 404가 나던 문제가 있었다.
//
// 이 테스트는 컨트롤러를 라우트를 거치지 않고 직접 호출한다 — /en/board/... 같은 언어별 라우트는
// routes/web.php가 부팅 시점의 Language 테이블 상태를 읽어 동적으로 등록하는데, RefreshDatabase를
// 쓰는 단위 테스트에서는 마이그레이션이 라우트 등록보다 나중에 실행되어 en 라우트 자체가 없는
// 것으로 취급되는 환경 제약이 있다(실제 운영에서는 문제 없음 — 실제 브라우저로도 별도 검증함).
// 컨트롤러를 직접 호출하면 이 제약과 무관하게 실제 수정된 쿼리 로직 자체를 검증할 수 있다.
class CommentLocaleScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_comment_store_resolves_the_board_matching_current_locale_when_slug_is_shared(): void
    {
        $member = User::create([
            'name' => 'Member', 'email' => 'member@test.local', 'password' => bcrypt('password'),
            'level' => User::LEVEL_MEMBER, 'is_active' => true,
        ]);

        $koBoard = Board::create(['name' => '채용공고', 'slug' => 'recruitment', 'locale' => 'ko', 'skin' => 'default', 'is_active' => true, 'allow_comment' => true]);
        $enBoard = Board::create(['name' => 'Recruitment', 'slug' => 'recruitment', 'locale' => 'en', 'skin' => 'default', 'is_active' => true, 'allow_comment' => true]);

        // ko 게시판에도 같은 id 충돌 가능성을 확인하기 위해 ko 쪽에도 글을 하나 심어둔다.
        Post::create(['board_id' => $koBoard->id, 'title' => 'KO post', 'content' => 'x', 'is_active' => true]);
        $enPost = Post::create(['board_id' => $enBoard->id, 'title' => 'EN post', 'content' => 'x', 'is_active' => true]);

        app()->setLocale('en');

        $request = Request::create(
            "/en/board/recruitment/{$enPost->id}/comment",
            'POST',
            ['content' => 'Locale scope check']
        );
        $request->setLaravelSession($this->app['session']->driver());
        $this->actingAs($member);
        $request->setUserResolver(fn () => $member);

        $response = app(CommentController::class)->store($request, 'recruitment', $enPost->id);

        $this->assertTrue($response->isRedirect(), '정상 처리되면 back()으로 리다이렉트되어야 함');
        $this->assertDatabaseHas('comments', [
            'post_id' => $enPost->id,
            'content' => 'Locale scope check',
            'user_id' => $member->id,
        ]);
    }
}
