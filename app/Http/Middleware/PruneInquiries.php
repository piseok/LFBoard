<?php

namespace App\Http\Middleware;

use App\Services\InquiryRetentionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

// PruneAiChatHistory와 동일한 패턴 — 크론 없는 공유호스팅 환경이라 관리자가 접속할 때
// (하루 한 번, 캐시로 중복 실행 방지) 보유기간이 지난 1:1 문의를 정리한다.
class PruneInquiries
{
    public function handle(Request $request, Closure $next): Response
    {
        $cacheKey = 'inquiry_prune_check_'.now()->format('Y-m-d');

        if (! Cache::has($cacheKey)) {
            Cache::put($cacheKey, true, now()->endOfDay());

            try {
                app(InquiryRetentionService::class)->pruneExpired();
            } catch (Throwable) {
                // 정리 작업 실패가 관리자 화면 접근 자체를 막으면 안 되므로 조용히 넘어간다.
            }
        }

        return $next($request);
    }
}
