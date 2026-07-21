<?php

namespace App\Http\Middleware;

use App\Services\AdminAuditLogService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

// 크론을 쓸 수 없는 공유호스팅 환경이라, 관리자가 접속할 때(하루 한 번, 캐시로 중복 실행 방지)
// 보관 기간이 지난 감사로그를 정리한다(ProcessDormantAccounts/ApplyScheduledPolicyChanges와 동일한 패턴).
class PruneAdminAuditLogs
{
    public function handle(Request $request, Closure $next): Response
    {
        $cacheKey = 'admin_audit_log_prune_check_'.now()->format('Y-m-d');

        if (! Cache::has($cacheKey)) {
            Cache::put($cacheKey, true, now()->endOfDay());

            try {
                app(AdminAuditLogService::class)->pruneExpired();
            } catch (Throwable) {
                // 정리 작업 실패가 관리자 화면 접근 자체를 막으면 안 되므로 조용히 넘어간다.
            }
        }

        return $next($request);
    }
}
