<?php

namespace App\Http\Middleware;

use App\Services\VendorNoticeSyncService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

// 크론을 쓸 수 없는 공유호스팅 환경이라, 관리자가 접속할 때(1시간에 한 번, 캐시로 중복 실행
// 방지) 관리업체 공지사항 API를 폴링한다(ProcessDormantAccounts와 동일한 패턴).
class SyncVendorNotices
{
    public function handle(Request $request, Closure $next): Response
    {
        $cacheKey = 'vendor_notice_sync_'.now()->format('Y-m-d-H');

        if (! Cache::has($cacheKey)) {
            Cache::put($cacheKey, true, now()->addHour());

            try {
                app(VendorNoticeSyncService::class)->sync();
            } catch (Throwable) {
                // 동기화 실패가 관리자 화면 접근 자체를 막으면 안 되므로 조용히 넘어간다.
            }
        }

        return $next($request);
    }
}
