<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasPermissionCheck;
use App\Filament\Widgets\VisitorChartWidget;
use App\Filament\Widgets\VisitStatsOverviewWidget;
use BackedEnum;
use UnitEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class VisitStats extends Page
{
    use HasPermissionCheck;

    protected static string $permissionKey = 'visit_stats';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = '방문자 통계';

    protected static string|UnitEnum|null $navigationGroup = '통계';

    protected static ?string $title = '방문자 통계';

    protected function getHeaderWidgets(): array
    {
        return [
            VisitStatsOverviewWidget::class,
            VisitorChartWidget::class,
        ];
    }
}
