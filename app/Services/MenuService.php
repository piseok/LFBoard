<?php

namespace App\Services;

use App\Models\Board;
use App\Models\Language;
use App\Models\Menu;
use App\Models\Page;
use Illuminate\Support\Collection;

class MenuService
{
    private Collection $boardSlugs;

    private Collection $pageSlugs;

    /**
     * 레벨 필터링된 메뉴 트리 구조 (최대 3단계)를 반환한다. 현재 언어(App::getLocale())로 스코프됨.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTree(int $userLevel = 1): array
    {
        $locale = app()->getLocale();

        // 'hidden' 모드는 레벨 미달 시 완전히 제외하고, 'locked' 모드는 레벨과 무관하게 항상 포함시킨 뒤
        // buildTree()에서 잠금 여부만 표시한다(실제 접근 차단은 연결된 게시판/페이지 자체의 레벨 체크에 맡김).
        $menus = Menu::query()
            ->where('is_active', true)
            ->where('locale', $locale)
            ->where(function ($query) use ($userLevel) {
                $query->where('min_level', '<=', $userLevel)->orWhere('access_mode', 'locked');
            })
            ->orderBy('sort_order')
            ->get();

        $this->boardSlugs = Board::query()->where('locale', $locale)->pluck('slug', 'id');
        $this->pageSlugs = Page::query()->where('locale', $locale)->pluck('slug', 'id');

        return $this->buildTree($menus, null, 0, $userLevel);
    }

    private function buildTree(Collection $menus, ?int $parentId, int $depth, int $userLevel): array
    {
        if ($depth >= 3) {
            return [];
        }

        return $menus
            ->where('parent_id', $parentId)
            ->map(function (Menu $menu) use ($menus, $depth, $userLevel) {
                return [
                    'id' => $menu->id,
                    'title' => $menu->title,
                    'url' => $this->resolveUrl($menu),
                    'target' => $menu->target,
                    'type' => $menu->type,
                    'locked' => $menu->access_mode === 'locked' && $menu->min_level > $userLevel,
                    // 헤더(GNB)에서만 숨긴다 — getTree()가 반환하는 전체 트리에는 그대로 포함되므로
                    // getActiveBranch()가 계산하는 로컬 내비게이션(LNB)에는 영향이 없다. 예: "마이페이지"를
                    // 헤더에서는 숨기고(헤더에 이미 별도 진입 링크가 있음) 그 하위 페이지에서만 LNB로 노출.
                    'hidden_from_header' => $menu->hidden_from_header,
                    'children' => $this->buildTree($menus, $menu->id, $depth + 1, $userLevel),
                ];
            })
            ->values()
            ->all();
    }

    // 게시판/페이지 라우트는 언어별로 이름이 다르게 등록되어 있으므로(예: board.index vs ja.board.index),
    // 현재 언어에 맞는 라우트 이름으로 링크를 생성해야 접두사(/ja/...)가 제대로 붙는다.
    private function resolveUrl(Menu $menu): string
    {
        $routePrefix = Language::routeNamePrefix();

        return match ($menu->type) {
            'board' => isset($this->boardSlugs[$menu->target_id])
                ? route("{$routePrefix}board.index", ['slug' => $this->boardSlugs[$menu->target_id]])
                : '#',
            'page' => isset($this->pageSlugs[$menu->target_id])
                ? route("{$routePrefix}page.show", ['slug' => $this->pageSlugs[$menu->target_id]])
                : '#',
            'none' => '#',
            default => $menu->url ?? '#',
        };
    }

    /**
     * 현재 경로가 속한 1뎁스(최상위) 메뉴와, 그 경로상의 활성 메뉴 ID 목록을 계산한다.
     * 서브페이지의 로컬 서브메뉴(LNB) 및 상단 메뉴의 현재 위치 강조 표시에 사용된다.
     *
     * @return array{top: ?array<string, mixed>, activeIds: array<int, int>}
     */
    public function getActiveBranch(string $currentPath, int $userLevel = 1): array
    {
        $tree = $this->getTree($userLevel);
        $target = $this->normalizePath($currentPath);
        $activeIds = [];

        foreach ($tree as $node) {
            if ($this->markActivePath($node, $target, $activeIds)) {
                return ['top' => $node, 'activeIds' => $activeIds];
            }
        }

        return ['top' => null, 'activeIds' => []];
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<int, int>  $activeIds
     */
    private function markActivePath(array $node, string $target, array &$activeIds): bool
    {
        $selfMatch = $this->urlMatches($node['url'], $target);
        $childMatch = false;

        foreach ($node['children'] as $child) {
            if ($this->markActivePath($child, $target, $activeIds)) {
                $childMatch = true;
            }
        }

        if ($selfMatch || $childMatch) {
            $activeIds[] = $node['id'];

            return true;
        }

        return false;
    }

    /**
     * 현재 경로까지의 전체 메뉴 경로(홈 제외, 최상위 → 현재)를 순서대로 반환한다 —
     * 브레드크럼(홈 > 커뮤니티 > 공지사항) 표시에 사용된다. activeIds(getActiveBranch())는
     * 안쪽에서 바깥쪽 순서로 쌓여서 브레드크럼에 그대로 쓰기엔 순서가 뒤집혀 있고 title/url도
     * 없어서, 트리를 다시 훑어 부모→자식 순서로 title/url을 직접 모으는 별도 메서드로 뺐다.
     *
     * @return array<int, array{title: string, url: string}>
     */
    public function getBreadcrumbTrail(string $currentPath, int $userLevel = 1): array
    {
        $tree = $this->getTree($userLevel);
        $target = $this->normalizePath($currentPath);
        $trail = [];

        foreach ($tree as $node) {
            if ($this->collectTrail($node, $target, $trail)) {
                return $trail;
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<int, array{title: string, url: string}>  $trail
     */
    private function collectTrail(array $node, string $target, array &$trail): bool
    {
        $trail[] = ['title' => $node['title'], 'url' => $node['url']];

        if ($this->urlMatches($node['url'], $target)) {
            return true;
        }

        foreach ($node['children'] as $child) {
            if ($this->collectTrail($child, $target, $trail)) {
                return true;
            }
        }

        array_pop($trail);

        return false;
    }

    private function urlMatches(string $nodeUrl, string $target): bool
    {
        if ($nodeUrl === '#') {
            return false;
        }

        $nodePath = $this->normalizePath($nodeUrl);

        if ($nodePath === '/') {
            return $target === '/';
        }

        return $target === $nodePath || str_starts_with($target, $nodePath.'/');
    }

    private function normalizePath(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '/';

        if (! str_starts_with($path, '/')) {
            $path = '/'.$path;
        }

        $path = rtrim($path, '/');

        return $path === '' ? '/' : $path;
    }
}
