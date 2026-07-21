<?php

namespace App\Filament\Widgets;

use App\Models\Inquiry;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestInquiriesWidget extends BaseWidget
{
    protected static ?string $heading = '최근 미답변 상담';

    protected int|string|array $columnSpan = 1;

    // 대시보드 위젯 다중 lazy 로드가 파일 세션 경합/419를 일으키는 문제 대응(VisitStatsOverviewWidget 참고).
    protected static bool $isLazy = false;

    // 5초 자동 폴링도 기본값이라 세션 경합/419가 반복 재발하는 원인이 된다 — 실시간 갱신 불필요.
    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->hasAdminPermission('inquiries');
    }

    public function table(Table $table): Table
    {
        $query = Inquiry::query()->where('status', '!=', 'done');

        if ($user = auth()->user()) {
            $query->visibleTo($user);
        }

        return $table
            ->query($query->latest()->limit(5))
            ->paginated(false)
            ->columns([
                TextColumn::make('type')
                    ->label('유형')
                    ->badge(),
                TextColumn::make('name')->label('이름'),
                TextColumn::make('title')
                    ->label('제목')
                    ->limit(40)
                    ->url(fn (?Inquiry $record): ?string => $record ? route('filament.admin.resources.inquiries.edit', ['record' => $record]) : null),
                TextColumn::make('created_at')->label('접수일')->dateTime('Y-m-d H:i'),
                TextColumn::make('status')
                    ->label('상태')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => '대기',
                        'processing' => '처리중',
                        'done' => '완료',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'danger',
                        'processing' => 'warning',
                        'done' => 'success',
                        default => 'gray',
                    }),
            ]);
    }
}
