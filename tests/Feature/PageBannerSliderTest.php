<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 채팅 지시(2026-09-14, "페이지별로 배너 이미지를 연동해서 슬라이드까지 하고싶은데") — 콘텐츠
// 페이지(FrontController::page())에 banner_group_key로 연결된 배너 그룹을 슬라이더로 보여준다.
// banner_group_key가 비어있으면 기존처럼 배너 섹션 자체가 없어야 한다(회귀 방지).
class PageBannerSliderTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_shows_linked_banner_group_as_a_slider(): void
    {
        $locale = app()->getLocale();

        Page::create([
            'title' => '회사 소개', 'slug' => 'about', 'locale' => $locale,
            'content_type' => 'editor', 'content' => '<p>본문</p>',
            'min_level' => 0, 'is_active' => true, 'sort_order' => 0,
            'banner_group_key' => 'about_top',
        ]);

        Banner::create([
            'group_key' => 'about_top', 'content_type' => 'image', 'locale' => $locale,
            'title' => '소개 배너', 'image_path' => 'uploads/images/about-banner.jpg',
            'is_active' => true, 'sort_order' => 0,
        ]);

        $response = $this->get(front_route('page.show', 'about'));

        $response->assertOk();
        $response->assertSee('about-banner.jpg', false);
    }

    public function test_page_without_a_banner_group_key_renders_no_banner_section(): void
    {
        $locale = app()->getLocale();

        Page::create([
            'title' => '이용약관', 'slug' => 'terms', 'locale' => $locale,
            'content_type' => 'editor', 'content' => '<p>약관 내용</p>',
            'min_level' => 0, 'is_active' => true, 'sort_order' => 0,
        ]);

        Banner::create([
            'group_key' => 'unrelated_group', 'content_type' => 'image', 'locale' => $locale,
            'title' => '관계없는 배너', 'image_path' => 'uploads/images/unrelated.jpg',
            'is_active' => true, 'sort_order' => 0,
        ]);

        $response = $this->get(front_route('page.show', 'terms'));

        $response->assertOk();
        $response->assertDontSee('unrelated.jpg', false);
        $response->assertDontSee('lf-slider', false);
    }
}
