<?php

namespace App\Filament\Resources\AiChatLogs\Pages;

use App\Filament\Resources\AiChatLogs\AiChatLogResource;
use App\Filament\Widgets\AiUsageTodayWidget;
use Filament\Resources\Pages\ListRecords;

class ListAiChatLogs extends ListRecords
{
    protected static string $resource = AiChatLogResource::class;

    public function getSubheading(): string
    {
        return '각 관리자는 관리자 패널의 AI 비서 위젯에서 자기 대화만 볼 수 있습니다. 여기서는 슈퍼관리자가 전체 관리자의 AI 사용 내역을 감독하고, 필요 시 삭제할 수 있습니다. 대화 안의 행동 카드는 읽기 전용으로만 보입니다.';
    }

    // 목록 위에 "오늘 관리자별 사용량"(일일 한도 기준)을 보여 준다.
    protected function getHeaderWidgets(): array
    {
        return [AiUsageTodayWidget::class];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }
}
