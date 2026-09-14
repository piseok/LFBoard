<?php

namespace Tests\Feature;

use App\Http\Controllers\FrontController;
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

    // 채팅 지시(2026-09-14, "content 안에있는 특정 부분에 이식하고싶은데") — 콘텐츠(에디터
    // 본문/업로드 HTML 파일)가 Blade를 거치지 않아 <x-slider>를 직접 못 쓰므로, 순수 텍스트
    // 플레이스홀더 토큰을 그 자리에 심어두면 슬라이더가 콘텐츠 맨 위 대신 그 위치에 삽입된다.
    public function test_banner_slider_is_spliced_into_content_at_the_placeholder_token(): void
    {
        $locale = app()->getLocale();

        Page::create([
            'title' => '연혁', 'slug' => 'history', 'locale' => $locale,
            'content_type' => 'editor',
            'content' => '<p>지역설명 위쪽</p><p>'.FrontController::BANNER_PLACEHOLDER.'</p><p>지역설명 아래쪽</p>',
            'min_level' => 0, 'is_active' => true, 'sort_order' => 0,
            'banner_group_key' => 'history_mid',
        ]);

        Banner::create([
            'group_key' => 'history_mid', 'content_type' => 'image', 'locale' => $locale,
            'title' => '연혁 배너', 'image_path' => 'uploads/images/history-banner.jpg',
            'is_active' => true, 'sort_order' => 0,
        ]);

        $response = $this->get(front_route('page.show', 'history'));

        $response->assertOk();
        $response->assertDontSee(FrontController::BANNER_PLACEHOLDER, false);
        $response->assertSeeInOrder(['지역설명 위쪽', 'history-banner.jpg', '지역설명 아래쪽'], false);
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
