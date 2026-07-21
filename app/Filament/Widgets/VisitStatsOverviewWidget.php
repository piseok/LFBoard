<?php

namespace App\Filament\Widgets;

use App\Models\VisitLog;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class VisitStatsOverviewWidget extends BaseWidget
{
    // Filament 위젯은 기본적으로 lazy(비동기 후속 요청)로 로드된다 — 한 페이지에 위젯이 여러 개면
    // 각자 따로 /livewire/update를 동시에 쏘게 되는데, 파일 기반 세션에서 이 동시 요청들이 세션
    // 파일 접근을 두고 경합하면서 한쪽만 CSRF 419로 실패하는 문제가 실제로 있었다. 이 위젯은
    // 단순 count 쿼리라 지연 로딩이 딱히 필요 없어서 꺼서 동시 요청 자체를 없앤다.
    protected static bool $isLazy = false;

    // Filament 위젯은 CanPoll 트레이트로 기본 5초마다 자동 폴링(wire:poll.5s)도 한다 — 방문자
    // 수치가 5초 단위로 실시간 갱신될 필요는 없고, 여러 위젯이 같은 페이지에서 동시에 폴링하면
    // 위와 같은 세션 경합/419가 계속 반복해서 재발한다. 폴링 자체를 꺼서 근본적으로 막는다.
    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        return [
            Stat::make('오늘 방문자수', VisitLog::whereDate('date', today())->count()),
            Stat::make('어제 방문자수', VisitLog::whereDate('date', today()->subDay())->count()),
            Stat::make('이번 달 방문자수', VisitLog::whereBetween('date', [now()->startOfMonth(), now()->endOfMonth()])->count()),
        ];
    }
}
