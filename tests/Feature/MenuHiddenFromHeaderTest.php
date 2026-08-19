<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\Policy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// "전체메뉴(헤더)에서 숨김" — 헤더/모바일 전체메뉴에서는 제외되지만, 그 브랜치의 서브페이지에서는
// 로컬 내비게이션(LNB)에 계속 표시되어야 한다(MenuService::getTree/getActiveBranch는 그대로 전체
// 포함, 헤더 Blade 템플릿만 필터링).
class MenuHiddenFromHeaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_hidden_menu_does_not_appear_in_header_but_visible_menu_does(): void
    {
        Menu::create([
            'title' => '마이페이지', 'locale' => 'ko', 'type' => 'url', 'url' => '/mypage',
            'min_level' => 0, 'sort_order' => 1, 'is_active' => true, 'hidden_from_header' => true,
        ]);
        Menu::create([
            'title' => '커뮤니티', 'locale' => 'ko', 'type' => 'url', 'url' => '/community',
            'min_level' => 0, 'sort_order' => 2, 'is_active' => true, 'hidden_from_header' => false,
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('마이페이지');
        $response->assertSee('커뮤니티');
    }

    public function test_hidden_top_menus_children_still_render_in_local_nav_on_a_matching_subpage(): void
    {
        Policy::create([
            'type' => 'terms', 'locale' => 'ko', 'title' => '이용약관', 'content' => '내용', 'is_active' => true,
        ]);

        $parent = Menu::create([
            'title' => '약관', 'locale' => 'ko', 'type' => 'url', 'url' => '/terms',
            'min_level' => 0, 'sort_order' => 1, 'is_active' => true, 'hidden_from_header' => true,
        ]);
        Menu::create([
            'parent_id' => $parent->id, 'title' => '이용약관 보기', 'locale' => 'ko', 'type' => 'url', 'url' => '/terms',
            'min_level' => 0, 'sort_order' => 1, 'is_active' => true,
        ]);

        $response = $this->get(front_route('policy.terms'));

        $response->assertOk();
        // 헤더(.gnb)에는 "약관"이 안 보여야 하지만(다른 테스트에서 검증), 이 서브페이지의 로컬
        // 내비게이션(LNB)에는 하위 메뉴("이용약관 보기")가 정상적으로 떠야 한다.
        $response->assertSee('이용약관 보기');
    }

    // "none"(링크 없음, 그룹 텍스트) 1뎁스 메뉴는 클릭해도 아무 데도 안 가는 href="#"였다 — 하위
    // 메뉴가 있으면 그 첫 번째 항목으로 이동하게 한다(2026-08-11 사용자 지시).
    public function test_linkless_top_menu_falls_back_to_first_childs_url(): void
    {
        $parent = Menu::create([
            'title' => '커뮤니티', 'locale' => 'ko', 'type' => 'none',
            'min_level' => 0, 'sort_order' => 1, 'is_active' => true,
        ]);
        Menu::create([
            'parent_id' => $parent->id, 'title' => '공지사항', 'locale' => 'ko', 'type' => 'url', 'url' => '/notice',
            'min_level' => 0, 'sort_order' => 1, 'is_active' => true,
        ]);
        Menu::create([
            'parent_id' => $parent->id, 'title' => '자유게시판', 'locale' => 'ko', 'type' => 'url', 'url' => '/free',
            'min_level' => 0, 'sort_order' => 2, 'is_active' => true,
        ]);

        $tree = app(\App\Services\MenuService::class)->getTree();

        $this->assertSame('/notice', $tree[0]['url']);
    }

    // 하위 메뉴가 아예 없으면(진짜 그룹 텍스트로만 쓰는 경우) 기존대로 '#'를 유지해야 한다 —
    // 갈 곳이 없는데 억지로 링크를 만들면 안 된다.
    public function test_linkless_top_menu_without_children_stays_hash(): void
    {
        Menu::create([
            'title' => '안내', 'locale' => 'ko', 'type' => 'none',
            'min_level' => 0, 'sort_order' => 1, 'is_active' => true,
        ]);

        $tree = app(\App\Services\MenuService::class)->getTree();

        $this->assertSame('#', $tree[0]['url']);
    }
}
