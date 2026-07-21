<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Models\Post;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class CommunityController extends Controller
{
    // 홈 화면 커뮤니티 위젯(partials.community-section, 라이브 서버 전용 커스텀 파일)의
    // "전체보기(+)" 링크가 도착하는 전용 페이지 — 여러 게시판(공지/FAQ/자료실 등)을 상단 탭으로
    // 묶어서 한 페이지에서 훑어볼 수 있게 한다. 탭은 SearchController와 마찬가지로 서버사이드
    // GET 파라미터(?board=slug) 방식이라 페이지네이션과도 자연스럽게 맞물린다.
    private const DEFAULT_SLUGS = ['notice', 'faq', 'archive'];

    public function index(Request $request): View
    {
        $userLevel = auth()->user()?->level ?? User::LEVEL_GUEST;

        $boards = Board::query()
            ->where('locale', app()->getLocale())
            ->where('is_active', true)
            ->where('min_read_level', '<=', $userLevel)
            ->where('exclude_from_search', false)
            ->whereIn('slug', self::DEFAULT_SLUGS)
            ->orderBy('sort_order')
            ->get()
            ->keyBy('slug');

        $activeBoardSlug = $request->string('board')->toString();
        $activeBoard = $boards->get($activeBoardSlug);

        $query = Post::query()
            ->whereIn('board_id', $boards->pluck('id'))
            ->where('is_active', true)
            ->where('is_draft', false);

        if ($activeBoard) {
            $query->where('board_id', $activeBoard->id);
        }

        $this->restrictSecretPosts($query, $request);

        // BoardFrontController::index()와 동일한 검색 방식(제목/내용/작성자) — 여러 게시판을
        // 한 번에 훑는 화면이라 게시판별 검색과 같은 옵션을 그대로 제공한다.
        $keyword = $request->string('q')->toString();
        if ($keyword !== '') {
            $type = $request->string('search_type', 'title')->toString();
            $query->where(function ($q) use ($type, $keyword) {
                match ($type) {
                    'content' => $q->where('content', 'like', "%{$keyword}%"),
                    'author' => $q->where('author_name', 'like', "%{$keyword}%")
                        ->orWhereHas('user', fn ($u) => $u->where('nickname', 'like', "%{$keyword}%")),
                    default => $q->where('title', 'like', "%{$keyword}%"),
                };
            });
        }

        $posts = $query->with('board')->orderByDesc('created_at')->paginate(20)->withQueryString();

        return view('community.index', [
            'boards' => $boards,
            'activeBoardSlug' => $activeBoard?->slug ?? '',
            'posts' => $posts,
            'keyword' => $keyword,
            'pageTitle' => __('커뮤니티'),
        ]);
    }

    // SearchController::restrictSecretPosts()와 동일한 규칙(비밀글은 작성자 본인/관리자만) —
    // 여러 게시판을 동시에 훑는 화면이라 게시판별 상세 목록과 같은 노출 규칙을 지켜야 하므로 복제.
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
