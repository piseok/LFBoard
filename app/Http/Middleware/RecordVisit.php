<?php

namespace App\Http\Middleware;

use App\Models\VisitLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecordVisit
{
    // 주요 크롤러 UA는 방문자 통계에서 제외한다.
    private const BOT_PATTERNS = [
        'bot', 'crawl', 'spider', 'slurp', 'googlebot', 'bingbot',
        'yandex', 'baiduspider', 'duckduckbot', 'facebookexternalhit',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $this->record($request);

        return $next($request);
    }

    private function record(Request $request): void
    {
        $userAgent = mb_strtolower((string) $request->userAgent());

        foreach (self::BOT_PATTERNS as $pattern) {
            if (str_contains($userAgent, $pattern)) {
                return;
            }
        }

        try {
            VisitLog::query()->insertOrIgnore([
                'date' => now()->toDateString(),
                'ip' => (string) $request->ip(),
                'user_id' => $request->user()?->id,
                'path' => $request->path(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable) {
            // 방문 기록 실패가 실제 요청 처리에 영향을 주지 않도록 무시한다.
        }
    }
}
