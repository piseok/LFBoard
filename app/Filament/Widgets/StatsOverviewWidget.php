<?php

namespace App\Filament\Widgets;

use App\Models\Inquiry;
use App\Models\Post;
use App\Models\User;
use App\Models\VisitLog;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    // 대시보드에 위젯이 여러 개 동시에 lazy 로드되면서 파일 세션 경합으로 하나가 419로 실패하는
    // 문제가 있었다(VisitStatsOverviewWidget 참고) — 가벼운 쿼리라 꺼서 동시 요청을 없앤다.
    protected static bool $isLazy = false;

    // 5초마다 자동 폴링(wire:poll.5s)도 기본값이라 여러 위젯이 계속 동시에 재요청하면서 세션
    // 경합/419가 반복 재발한다 — 실시간 갱신이 필요 없어서 끈다.
    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user && (
            $user->hasAdminPermission('users')
            || $user->hasAdminPermission('posts')
            || $user->hasAdminPermission('inquiries')
            || $user->hasAdminPermission('visit_stats')
        );
    }

    protected function getStats(): array
    {
        $user = auth()->user();
        $stats = [];

        if ($user?->hasAdminPermission('users')) {
            $stats[] = Stat::make('전체 회원수', User::count())
                ->description('오늘 가입 '.User::whereDate('created_at', today())->count().'명')
                ->color('primary');
        }

        if ($user?->hasAdminPermission('posts')) {
            $postCount = Post::query()->visibleTo($user)->count();
            $todayPostCount = Post::query()->visibleTo($user)->whereDate('created_at', today())->count();

            $stats[] = Stat::make('전체 게시글수', $postCount)
                ->description('오늘 작성 '.$todayPostCount.'건')
                ->color('success');
        }

        if ($user?->hasAdminPermission('inquiries')) {
            $stats[] = Stat::make('미답변 상담', Inquiry::query()->visibleTo($user)->where('status', 'pending')->count())
                ->description('처리 대기 중')
                ->color('danger');
        }

        if ($user?->hasAdminPermission('visit_stats')) {
            $description = $user->hasAdminPermission('posts')
                ? '전체 페이지뷰 '.number_format((int) Post::query()->visibleTo($user)->sum('views'))
                : '오늘 방문';

            $stats[] = Stat::make('오늘 방문자수', VisitLog::whereDate('date', today())->count())
                ->description($description)
                ->color('warning');
        }

        return $stats;
    }
}
