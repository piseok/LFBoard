<?php

namespace App\Filament\Widgets;

use FilesystemIterator;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

class ServerStorageWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 1;

    // 대시보드 위젯 다중 lazy 로드가 파일 세션 경합/419를 일으키는 문제 대응(VisitStatsOverviewWidget
    // 참고) — 값 자체도 1800초 캐시라 매 요청 지연 로딩할 이유가 없다.
    protected static bool $isLazy = false;

    // 5초 자동 폴링도 기본값인데 값이 1800초 캐시라 어차피 대부분 캐시 히트일 뿐 — 불필요한
    // 반복 요청과 세션 경합 위험만 남기므로 끈다.
    protected ?string $pollingInterval = null;

    // 업로드 파일 용량 / DB 용량은 앱이 직접 통제하는 값이라 정확하지만, disk_free_space()는 공유호스팅에서
    // 계정 자체의 할당량이 아니라 서버 전체(다른 이용자와 공유하는 물리 디스크)의 여유 공간을 반환하는 경우가
    // 흔해서 참고용으로만 안내한다. PHP 함수 자체가 호스팅사 설정(disable_functions)으로 막혀 있을 수도 있어
    // 실패 시 "조회 불가"로 안전하게 표시한다.
    protected function getStats(): array
    {
        $uploadsBytes = Cache::remember('server_storage.uploads_size', 1800, fn () => $this->directorySize(public_path('uploads')));
        $dbBytes = Cache::remember('server_storage.db_size', 1800, fn () => $this->databaseSize());
        $freeBytes = $this->diskFreeSpace();

        return [
            Stat::make('업로드 파일 용량', $this->formatBytes($uploadsBytes))
                ->description('public/uploads 폴더 전체 크기')
                ->color('primary'),

            Stat::make('데이터베이스 용량', $dbBytes !== null ? $this->formatBytes($dbBytes) : '조회 불가')
                ->description('전체 테이블 데이터+인덱스 합계')
                ->color('success'),

            Stat::make('서버 디스크 여유 공간', $freeBytes !== null ? $this->formatBytes($freeBytes) : '조회 불가')
                ->description('공유호스팅에서는 계정 할당량이 아닌 서버 전체 여유 공간일 수 있어 참고용입니다')
                ->color('warning'),
        ];
    }

    private function directorySize(string $path, int $maxFiles = 20000): int
    {
        if (! is_dir($path)) {
            return 0;
        }

        $size = 0;
        $count = 0;

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $size += $file->getSize();
                    $count++;

                    // 파일이 매우 많은 경우 대시보드 로딩이 느려지지 않도록 상한을 둔다(대략적인 값으로 충분).
                    if ($count >= $maxFiles) {
                        break;
                    }
                }
            }
        } catch (Throwable) {
            return $size;
        }

        return $size;
    }

    private function databaseSize(): ?int
    {
        try {
            $row = DB::selectOne(
                'SELECT SUM(data_length + index_length) AS size FROM information_schema.tables WHERE table_schema = ?',
                [DB::getDatabaseName()]
            );

            return $row?->size !== null ? (int) $row->size : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function diskFreeSpace(): ?int
    {
        $free = @disk_free_space(base_path());

        return $free === false ? null : (int) $free;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $value = $bytes;

        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }

        return number_format($value, $i === 0 ? 0 : 1).' '.$units[$i];
    }
}
