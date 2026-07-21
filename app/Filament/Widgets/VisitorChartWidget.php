<?php

namespace App\Filament\Widgets;

use App\Models\VisitLog;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class VisitorChartWidget extends ChartWidget
{
    protected ?string $heading = '일별 방문자 수 (최근 30일)';

    protected int|string|array $columnSpan = 'full';

    // VisitStatsOverviewWidget과 같은 페이지에 있어 두 위젯이 각자 lazy 로드를 동시에 시도하면
    // 파일 세션 경합으로 한쪽이 419로 실패하는 문제가 있었다 — 지연 로딩이 필요 없는 가벼운
    // 쿼리라 꺼서 동시 요청 자체를 없앤다.
    protected static bool $isLazy = false;

    // 5초마다 자동 폴링(wire:poll.5s)도 기본값이라 여러 위젯이 동시에 계속 재요청하면서 세션
    // 경합/419가 반복 재발한다 — 방문자 차트가 5초 단위로 실시간 갱신될 필요는 없어서 끈다.
    protected ?string $pollingInterval = null;

    protected function getData(): array
    {
        $days = collect(range(29, 0))->map(fn (int $i) => Carbon::now()->subDays($i)->toDateString());

        // (date, ip) unique 제약이므로 날짜별 count가 곧 순 방문자 수
        $counts = VisitLog::query()
            ->whereBetween('date', [$days->first(), $days->last()])
            ->selectRaw('date, count(*) as visitor_count')
            ->groupBy('date')
            ->pluck('visitor_count', 'date');

        return [
            'datasets' => [
                [
                    'label' => '방문자수',
                    'data' => $days->map(fn (string $day) => (int) ($counts[$day] ?? 0))->all(),
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.2)',
                ],
            ],
            'labels' => $days->map(fn (string $day) => substr($day, 5))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
