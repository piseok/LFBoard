<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 채팅 지시(2026-09-14, "메인에는 내가 지정한 타입만 나오게 하고 각 타입별로 버튼이 나오고
// 전체가 있어, 스와이프 나오게 하고싶어") — 게시판이 아니라 배너관리 그룹 키 기준 탭+슬라이드.
// SiteSettings의 home_banner_tab_groups가 비어있으면 섹션 자체가 없어야 한다(회귀 방지).
class HomeBannerTabsTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_shows_a_tab_per_configured_banner_group_plus_an_all_tab(): void
    {
        $locale = app()->getLocale();

        SiteSetting::create(['key' => 'home_banner_tab_groups', 'value' => '인테리어, 욕실']);

        Banner::create([
            'group_key' => '인테리어', 'content_type' => 'image', 'locale' => $locale,
            'title' => '인테리어 배너', 'image_path' => 'uploads/images/interior-banner.jpg',
            'is_active' => true, 'sort_order' => 0,
        ]);
        Banner::create([
            'group_key' => '욕실', 'content_type' => 'image', 'locale' => $locale,
            'title' => '욕실 배너', 'image_path' => 'uploads/images/bathroom-banner.jpg',
            'is_active' => true, 'sort_order' => 0,
        ]);
        Banner::create([
            'group_key' => 'unrelated_group', 'content_type' => 'image', 'locale' => $locale,
            'title' => '관계없는 배너', 'image_path' => 'uploads/images/unrelated-banner.jpg',
            'is_active' => true, 'sort_order' => 0,
        ]);

        $response = $this->get(front_route('home'));

        $response->assertOk();
        $response->assertSee('main-cases', false);
        $response->assertSee('전체');
        $response->assertSee('인테리어');
        $response->assertSee('욕실');
        $response->assertSee('interior-banner.jpg', false);
        $response->assertSee('bathroom-banner.jpg', false);
        // "전체" 탭도 지정된 그룹만 합친 것이지 시스템 전체 배너가 아니다.
        $response->assertDontSee('unrelated-banner.jpg', false);
    }

    public function test_home_renders_no_banner_tabs_section_when_not_configured(): void
    {
        $response = $this->get(front_route('home'));

        $response->assertOk();
        $response->assertDontSee('main-cases', false);
    }
}
