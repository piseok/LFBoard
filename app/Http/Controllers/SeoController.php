<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Models\Language;
use App\Models\Page;
use App\Models\Policy;
use App\Models\Post;
use App\Services\SiteSettingService;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function robots(): Response
    {
        $settings = app(SiteSettingService::class);
        $content = (string) $settings->get('robots_txt', "User-agent: *\nAllow: /");

        if ($settings->get('sitemap_enabled') === '1') {
            $content = rtrim($content)."\n\nSitemap: ".route('seo.sitemap');
        }

        return response($content, 200)->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    // sitemap.xml — 언어별 사이트맵(아래 sitemap())을 모아 알려주는 인덱스. 언어가 하나뿐이어도
    // 구조를 통일해 두면 나중에 언어가 추가돼도 이 컨트롤러를 안 건드려도 된다.
    public function sitemapIndex(): Response
    {
        $settings = app(SiteSettingService::class);

        abort_unless($settings->get('sitemap_enabled') === '1', 404);

        $sitemaps = Language::query()->where('is_active', true)->orderBy('sort_order')->pluck('code')
            ->map(fn (string $code) => route('seo.sitemap.locale', ['locale' => $code]));

        $xml = view('seo.sitemap-index', ['sitemaps' => $sitemaps])->render();

        return response($xml, 200)->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    // sitemap-{locale}.xml — 해당 언어의 콘텐츠만 그 언어 URL(라우트 접두사 포함)로 나열한다.
    // 언어가 섞이면 검색엔진이 "같은 페이지가 중복 등록됐다"고 오인할 수 있어 반드시 분리한다.
    public function sitemap(string $locale): Response
    {
        $settings = app(SiteSettingService::class);

        abort_unless($settings->get('sitemap_enabled') === '1', 404);
        abort_unless(Language::query()->where('code', $locale)->where('is_active', true)->exists(), 404);

        $prefix = Language::routeNamePrefix($locale);

        $urls = collect([
            ['loc' => route($prefix.'home'), 'priority' => '1.0'],
        ]);

        foreach (Page::query()->where('is_active', true)->where('locale', $locale)->get() as $page) {
            $urls->push(['loc' => route($prefix.'page.show', $page->slug), 'priority' => '0.6']);
        }

        foreach (Board::query()->where('is_active', true)->where('locale', $locale)->get() as $board) {
            $urls->push(['loc' => route($prefix.'board.index', $board->slug), 'priority' => '0.7']);
        }

        $policyRoutes = ['terms' => 'policy.terms', 'privacy' => 'policy.privacy', 'marketing' => 'policy.marketing'];
        foreach (Policy::query()->where('is_active', true)->where('locale', $locale)->get() as $policy) {
            if (isset($policyRoutes[$policy->type])) {
                $urls->push(['loc' => route($prefix.$policyRoutes[$policy->type]), 'priority' => '0.3']);
            }
        }

        $boardSlugs = Board::query()->where('is_active', true)->where('locale', $locale)->pluck('slug', 'id');

        $posts = Post::query()
            ->whereIn('board_id', $boardSlugs->keys())
            ->where('is_active', true)
            ->where('is_draft', false)
            ->where('is_secret', false)
            ->latest()
            ->limit(500)
            ->get(['id', 'board_id', 'updated_at']);

        foreach ($posts as $post) {
            $urls->push([
                'loc' => route($prefix.'board.show', ['slug' => $boardSlugs[$post->board_id], 'id' => $post->id]),
                'priority' => '0.5',
                'lastmod' => $post->updated_at->toAtomString(),
            ]);
        }

        $xml = view('seo.sitemap', ['urls' => $urls])->render();

        return response($xml, 200)->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
