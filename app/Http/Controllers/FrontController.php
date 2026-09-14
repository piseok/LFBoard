<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Models\Page;
use App\Models\User;
use App\Services\HtmlSanitizerService;
use App\Services\MenuService;
use App\Services\SiteSettingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;

class FrontController extends Controller
{
    // 관리자가 에디터 본문/업로드 HTML 파일 안의 원하는 위치에 이 토큰을 직접 써넣으면, 배너가
    // 콘텐츠 맨 위 대신 그 자리에 삽입된다(page()의 치환 로직 참고). 두 콘텐츠 타입 모두 Blade를
    // 거치지 않고 원문 그대로 출력되므로(html_file은 애초에 sanitize도 안 됨) <x-slider> 태그를
    // 직접 못 쓰는 대신 순수 텍스트 토큰으로 위치만 표시하게 함.
    public const BANNER_PLACEHOLDER = '[[배너슬라이더]]';

    public function index(SiteSettingService $siteSettings): View
    {
        $banners = $this->activeBanners('main_top');
        $bannerTabs = $this->buildBannerTabs($siteSettings);

        return view('home.index', compact('banners', 'bannerTabs'));
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

        // 콘텐츠 안에 BANNER_PLACEHOLDER 토큰이 있으면 그 자리에 슬라이더를 삽입하고, page.show.
        // blade.php의 기본 노출(콘텐츠 맨 위)은 생략한다 — $banners를 비워서 그쪽 @if를 자연스럽게 끔.
        if ($banners->isNotEmpty() && str_contains((string) $content, self::BANNER_PLACEHOLDER)) {
            $content = str_replace(
                self::BANNER_PLACEHOLDER,
                Blade::render('<x-slider :items="$banners" aria-label="{{ __(\'배너\') }}" />', ['banners' => $banners]),
                $content
            );
            $banners = collect();
        }

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

    // 채팅 지시(2026-09-14, "메인에는 내가 지정한 타입만 나오게 하고 각 타입별로 버튼이 나오고
    // 전체가 있어, 스와이프 나오게 하고싶어" — 게시판이 아니라 배너관리 그룹 키 기준으로 필터링).
    // SiteSettings의 home_banner_tab_groups(쉼표구분 배너 group_key 목록)에 지정된 그룹만 탭으로
    // 노출하고, "전체" 탭은 그 지정된 그룹들을 합친 결과를 보여준다(시스템 전체 배너가 아님).
    // 값이 비어있으면 빈 컬렉션을 반환해 home/index.blade.php의 섹션 자체가 렌더링되지 않는다.
    private function buildBannerTabs(SiteSettingService $siteSettings): Collection
    {
        $groupKeys = collect(explode(',', (string) $siteSettings->get('home_banner_tab_groups', '')))
            ->map(fn (string $key) => trim($key))
            ->filter()
            ->unique()
            ->values();

        if ($groupKeys->isEmpty()) {
            return collect();
        }

        $groups = $groupKeys->map(fn (string $groupKey) => [
            'name' => $groupKey,
            'banners' => $this->activeBanners($groupKey),
        ]);

        $allBanners = $groups->pluck('banners')->flatten(1)->unique('id')->sortBy('sort_order')->values();

        return collect([['name' => __('전체'), 'banners' => $allBanners]])
            ->concat($groups)
            ->values()
            // 그룹 이름을 한글로 자유롭게 입력할 수 있어(Str::slug()는 비ASCII를 비워버림) DOM
            // id/JS 훅용 key는 그룹 이름과 무관하게 인덱스 기반으로 안전하게 생성한다.
            ->map(fn (array $tab, int $index) => [...$tab, 'key' => "tab-{$index}"]);
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
