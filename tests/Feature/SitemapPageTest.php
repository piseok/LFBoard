<?php

namespace Tests\Feature;

use App\Models\Menu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// sitemap.xml(SeoController, 검색엔진용)과 별개로, /sitemap은 사람이 브라우저에서 볼 수 있는
// 사이트 전체 구조 페이지다. hidden_from_header 메뉴(예: 마이페이지 그룹)는 상단메뉴(GNB)와
// 똑같이 사이트맵에서도 숨긴다(사용자 확정 사항 — 헤더에 이미 별도 진입 링크가 있음).
class SitemapPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_page_excludes_menus_hidden_from_the_header(): void
    {
        Menu::create([
            'title' => '마이페이지', 'locale' => 'ko', 'type' => 'url', 'url' => '/mypage',
            'min_level' => 0, 'sort_order' => 1, 'is_active' => true, 'hidden_from_header' => true,
        ]);
        Menu::create([
            'title' => '커뮤니티', 'locale' => 'ko', 'type' => 'url', 'url' => '/community',
            'min_level' => 0, 'sort_order' => 2, 'is_active' => true, 'hidden_from_header' => false,
        ]);

        $response = $this->get(front_route('sitemap'));

        $response->assertOk();
        $response->assertDontSee('마이페이지');
        $response->assertSee('커뮤니티');
    }

    public function test_sitemap_page_renders_nested_child_menus(): void
    {
        $parent = Menu::create([
            'title' => '교육안내', 'locale' => 'ko', 'type' => 'none',
            'min_level' => 0, 'sort_order' => 1, 'is_active' => true,
        ]);
        Menu::create([
            'parent_id' => $parent->id, 'title' => '교육일정', 'locale' => 'ko', 'type' => 'url', 'url' => '/course/schedule',
            'min_level' => 0, 'sort_order' => 1, 'is_active' => true,
        ]);

        $response = $this->get(front_route('sitemap'));

        $response->assertOk()->assertSee('교육안내')->assertSee('교육일정');
    }

    public function test_sitemap_page_excludes_menus_below_the_current_users_level(): void
    {
        Menu::create([
            'title' => '회원전용', 'locale' => 'ko', 'type' => 'url', 'url' => '/members-only',
            'min_level' => 5, 'sort_order' => 1, 'is_active' => true,
        ]);

        $response = $this->get(front_route('sitemap'));

        $response->assertOk()->assertDontSee('회원전용');
    }
}
