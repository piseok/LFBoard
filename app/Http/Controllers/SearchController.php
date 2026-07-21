<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Models\Post;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    // 홈 화면 검색창 → 통합검색 결과. 지금 언어(app()->getLocale())에 속한, 이 방문자가 읽을 수
    // 있는 게시판만 대상으로 하고, 게시판별 탭으로 묶어서 보여준다(게시판 하나씩 따로 검색하는
    // BoardFrontController::index()의 검색과 달리 여러 게시판을 한 번에 훑어야 하므로 별도 컨트롤러로 분리).
    public function index(Request $request): View
    {
        $keyword = $request->string('q')->toString();
        $userLevel = auth()->user()?->level ?? User::LEVEL_GUEST;

        $boards = Board::query()
            ->where('locale', app()->getLocale())
            ->where('is_active', true)
            ->where('min_read_level', '<=', $userLevel)
            ->where('exclude_from_search', false)
            ->orderBy('sort_order')
            ->get();

        $resultsByBoard = collect();

        if ($keyword !== '') {
            foreach ($boards as $board) {
                $query = Post::query()
                    ->where('board_id', $board->id)
                    ->where('is_active', true)
                    ->where('is_draft', false)
                    ->where('title', 'like', "%{$keyword}%");

                $this->restrictSecretPosts($query, $request);

                $count = (clone $query)->count();

                if ($count > 0) {
                    $resultsByBoard->put($board->slug, [
                        'board' => $board,
                        'count' => $count,
                        'posts' => $query->orderByDesc('created_at')->limit(20)->get(),
                    ]);
                }
            }
        }

        $activeBoardSlug = $request->string('board')->toString() ?: $resultsByBoard->keys()->first();

        return view('search.index', [
            'keyword' => $keyword,
            'resultsByBoard' => $resultsByBoard,
            'activeBoardSlug' => $activeBoardSlug,
            'totalCount' => $resultsByBoard->sum('count'),
            'pageTitle' => __('통합검색'),
        ]);
    }

    // BoardFrontController::restrictSecretPosts()와 동일한 규칙(비밀글은 작성자 본인/관리자만) —
    // 여러 게시판을 동시에 훑는 통합검색에서도 게시판별 검색과 같은 노출 규칙을 지켜야 하므로 복제.
    private function restrictSecretPosts(Builder $query, Request $request): void
    {
        if ($request->user()?->level === User::LEVEL_ADMIN) {
            return;
        }

        $userId = $request->user()?->id;

        $query->where(function ($q) use ($userId) {
            $q->where('is_secret', false);

            if ($userId) {
                $q->orWhere('user_id', $userId);
            }
        });
    }
}
