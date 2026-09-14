<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Models\Page;
use App\Models\User;
use App\Services\HtmlSanitizerService;
use App\Services\MenuService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;

class FrontController extends Controller
{
    public function index(): View
    {
        $banners = $this->activeBanners('main_top');

        return view('home.index', compact('banners'));
    }

    public function page(string $slug, HtmlSanitizerService $sanitizer): View|RedirectResponse
    {
        $page = Page::query()
            ->where('slug', $slug)
            ->where('locale', app()->getLocale())
            ->where('is_active', true)
            ->firstOrFail();

        $userLevel = auth()->user()?->level ?? User::LEVEL_GUEST;
        if ($userLevel < $page->min_level) {
            return redirect(front_route('login'))->with('status', __('접근 권한이 없습니다. 로그인 후 이용해 주세요.'));
        }

        // 'html' 타입은 관리자가 올린 정적 HTML 파일을 그대로 페이지 안에 삽입한다(예전엔
        // <iframe src="...">로 별도 문서로 띄웠으나, 사이트 CSS/스크립트를 그대로 물려받게
        // include 방식으로 바꿨다). Banner의 html_content와 같은 이유로 sanitize하지 않는다 —
        // 관리자만 올릴 수 있는 신뢰된 콘텐츠라는 전제.
        $content = match ($page->content_type) {
            'editor' => $sanitizer->clean((string) $page->content),
            'html_file' => $page->html_file_path ? (string) Storage::disk('uploads')->get($page->html_file_path) : null,
            default => null,
        };

        // banner_group_key가 비어있으면 배너 섹션 자체를 렌더링하지 않는다(페이지별 선택 연동).
        $banners = $page->banner_group_key ? $this->activeBanners($page->banner_group_key) : collect();

        return view('page.show', [
            'page' => $page,
            'content' => $content,
            'banners' => $banners,
            'pageTitle' => $page->meta_title ?: $page->title,
            'pageDescription' => $page->meta_description,
            'pageKeywords' => $page->meta_keywords,
            'pageOgImage' => $page->og_image,
        ]);
    }

    // index()의 메인 히어로 배너 조회와 페이지별 배너 조회가 노출 조건(로케일/활성/기간)을
    // 그대로 공유하므로, group_key만 받아 재사용한다.
    private function activeBanners(string $groupKey)
    {
        return Banner::query()
            ->where('group_key', $groupKey)
            ->where('locale', app()->getLocale())
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('started_at')->orWhere('started_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ended_at')->orWhere('ended_at', '>=', now());
            })
            ->orderBy('sort_order')
            ->get();
    }

    // 검색엔진용 sitemap.xml(SeoController)과 별개로, 사람이 브라우저에서 전체 사이트 구조를
    // 볼 수 있는 화면. hidden_from_header 필터링은 sitemap.tree 뷰에서 partials.layout.menu-items와
    // 동일하게 처리한다(사용자 확정 사항: 상단메뉴와 사이트맵이 항상 똑같이 보여야 함).
    public function sitemap(): View
    {
        $userLevel = auth()->user()?->level ?? User::LEVEL_GUEST;
        $tree = app(MenuService::class)->getTree($userLevel);

        return view('sitemap.index', ['tree' => $tree, 'pageTitle' => __('사이트맵')]);
    }
}
