<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\AdminAuditLogService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RecordAdminAccess
{
    // 관리자가 접속하는 모든 페이지에 시간/IP/계정 로그를 남긴다. 관리자 활동 감사로그와 같은
    // 테이블(admin_audit_logs, action='access')에 쌓이므로 보관기간/자동정리는
    // PruneAdminAuditLogs 미들웨어 하나로 통일되어 있다(예전에는 이 미들웨어가 7일 고정으로
    // 별도 정리를 했었으나, 감사로그와 합치면서 그 로직은 제거했다).
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->level === User::LEVEL_ADMIN && $request->isMethod('GET')) {
            try {
                app(AdminAuditLogService::class)->recordAccess($user, $request->ip(), $request->fullUrl());
            } catch (Throwable) {
                // 로그 기록 실패가 관리자 화면 접근 자체를 막으면 안 되므로 조용히 넘어간다.
            }
        }

        return $next($request);
    }
}
