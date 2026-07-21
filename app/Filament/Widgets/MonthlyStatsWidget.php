<?php

namespace App\Filament\Widgets;

use App\Models\Post;
use App\Models\User;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class MonthlyStatsWidget extends ChartWidget
{
    protected ?string $heading = '월별 가입/게시글 통계 (최근 6개월)';

    protected int|string|array $columnSpan = 1;

    // 대시보드 위젯 다중 lazy 로드가 파일 세션 경합/419를 일으키는 문제 대응(VisitStatsOverviewWidget 참고).
    protected static bool $isLazy = false;

    // 5초 자동 폴링도 기본값이라 세션 경합/419가 반복 재발하는 원인이 된다 — 실시간 갱신 불필요.
    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user && ($user->hasAdminPermission('users') || $user->hasAdminPermission('posts'));
    }

    protected function getData(): array
    {
        $user = auth()->user();
        $months = collect(range(5, 0))->map(fn (int $i) => Carbon::now()->subMonths($i)->startOfMonth());

        $labels = $months->map(fn (Carbon $month) => $month->format('Y-m'))->all();
        $datasets = [];

        if ($user?->hasAdminPermission('users')) {
            $userCounts = $months->map(function (Carbon $month) {
                return User::whereBetween('created_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])->count();
            })->all();

            $datasets[] = [
                'label' => '가입 회원수',
                'data' => $userCounts,
                'borderColor' => '#f59e0b',
                'backgroundColor' => 'rgba(245, 158, 11, 0.2)',
            ];
        }

        if ($user?->hasAdminPermission('posts')) {
            $postCounts = $months->map(function (Carbon $month) use ($user) {
                return Post::query()->visibleTo($user)
                    ->whereBetween('created_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
                    ->count();
            })->all();

            $datasets[] = [
                'label' => '게시글수',
                'data' => $postCounts,
                'borderColor' => '#3b82f6',
                'backgroundColor' => 'rgba(59, 130, 246, 0.2)',
            ];
        }

        return [
            'datasets' => $datasets,
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
