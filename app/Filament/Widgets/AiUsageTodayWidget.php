<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\AiChatLogs\AiChatLogResource;
use App\Services\AiUsageService;
use Filament\Widgets\Widget;

// AI 비서 사용 내역(감독) 화면 머리말의 "오늘 관리자별 사용량" - ai_chat_usage 표로만 계산한다(API 호출 없음).
// 패널의 대시보드 위젯 목록(AdminPanelProvider::widgets)에는 넣지 않는다 - 이 리소스의 목록 페이지에서만 쓴다.
class AiUsageTodayWidget extends Widget
{
    protected string $view = 'filament.widgets.ai-usage-today';

    protected int|string|array $columnSpan = 'full';

    // 다른 위젯과 마찬가지로 지연 로딩/자동 폴링을 끈다(파일 세션 경합으로 419 가 나던 문제 대응).
    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return AiChatLogResource::canAccess();
    }

    protected function getViewData(): array
    {
        return [
            'summary' => app(AiUsageService::class)->todaySummary(),
            'usage' => app(AiUsageService::class),
        ];
    }
}
