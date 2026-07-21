<?php

namespace App\Filament\Widgets;

use App\Models\Post;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestPostsWidget extends BaseWidget
{
    protected static ?string $heading = '최근 게시글';

    protected int|string|array $columnSpan = 1;

    // 대시보드 위젯 다중 lazy 로드가 파일 세션 경합/419를 일으키는 문제 대응(VisitStatsOverviewWidget 참고).
    protected static bool $isLazy = false;

    // 5초 자동 폴링도 기본값이라 세션 경합/419가 반복 재발하는 원인이 된다 — 실시간 갱신 불필요.
    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->hasAdminPermission('posts');
    }

    public function table(Table $table): Table
    {
        $query = Post::query();

        if ($user = auth()->user()) {
            $query->visibleTo($user);
        }

        return $table
            ->query($query->latest()->limit(5))
            ->paginated(false)
            ->columns([
                TextColumn::make('board.name')->label('게시판'),
                TextColumn::make('title')
                    ->label('제목')
                    ->limit(40)
                    ->url(fn (?Post $record): ?string => $record ? route('filament.admin.resources.posts.edit', ['record' => $record]) : null),
                TextColumn::make('author')
                    ->label('작성자')
                    ->state(fn (?Post $record): ?string => $record ? ($record->user?->name ?? $record->author_name ?? '비회원') : null),
                TextColumn::make('created_at')->label('작성일')->dateTime('Y-m-d H:i'),
            ]);
    }
}
