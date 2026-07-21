<?php

namespace App\Http\Middleware;

use App\Services\AiChatService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

// PruneAdminAuditLogs와 동일한 패턴 — 크론 없는 공유호스팅 환경이라 관리자가 접속할 때
// (하루 한 번, 캐시로 중복 실행 방지) 보관 기간이 지난 AI 채팅 기록을 정리한다.
class PruneAiChatHistory
{
    public function handle(Request $request, Closure $next): Response
    {
        $cacheKey = 'ai_chat_history_prune_check_'.now()->format('Y-m-d');

        if (! Cache::has($cacheKey)) {
            Cache::put($cacheKey, true, now()->endOfDay());

            try {
                app(AiChatService::class)->pruneExpired();
            } catch (Throwable) {
                // 정리 작업 실패가 관리자 화면 접근 자체를 막으면 안 되므로 조용히 넘어간다.
            }
        }

        return $next($request);
    }
}
