<?php

namespace App\Http\Middleware;

use App\Models\Language;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

// URL 첫 세그먼트가 활성화된 언어 코드(기본 언어 제외)와 일치하면 그 언어로, 아니면 기본 언어로 설정한다.
// 실제 라우트 자체(접두사 있는/없는 버전)는 routes/web.php에서 언어별로 미리 등록해두므로,
// 이 미들웨어는 App::setLocale()과 "언어별 뷰 오버라이드 경로" 등록만 담당한다
// (resources/views/{locale}/... 파일이 있으면 그걸 우선 쓰고, 없으면 기본 뷰로 자동 폴백).
class DetectLocale
{
    private const CACHE_KEY = 'languages.active';

    public function handle(Request $request, Closure $next): Response
    {
        $languages = $this->activeLanguages();
        $defaultCode = $languages->firstWhere('is_default', true)['code'] ?? 'ko';

        $firstSegment = $request->segment(1);
        $matched = $languages->firstWhere('code', $firstSegment);

        $locale = ($matched && ! $matched['is_default']) ? $matched['code'] : $defaultCode;

        App::setLocale($locale);

        if ($locale !== $defaultCode) {
            View::prependLocation(resource_path("views/{$locale}"));
        }

        return $next($request);
    }

    /**
     * @return Collection<int, array{code: string, is_default: bool}>
     */
    private function activeLanguages()
    {
        return Cache::remember(
            self::CACHE_KEY,
            3600,
            fn () => Language::query()->where('is_active', true)->get(['code', 'is_default'])->map->only(['code', 'is_default'])
        );
    }
}
