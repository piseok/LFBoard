<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\RequiresSuperAdmin;
use BackedEnum;
use UnitEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class ErrorLogViewer extends Page
{
    use RequiresSuperAdmin;

    protected string $view = 'filament.pages.error-log-viewer';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static ?string $navigationLabel = '에러 로그';

    protected static string|UnitEnum|null $navigationGroup = '시스템 설정';

    protected static ?int $navigationSort = 70;

    protected static ?string $title = '에러 로그';

    // 공유호스팅에서는 서버 로그 파일에 FTP로만 접근 가능하거나 아예 접근이 불가능한 경우가 많아,
    // 로그 파일의 최근 내용을 관리자 화면에서 바로 확인할 수 있게 한다.
    // 로그 채널이 daily로 바뀌면서(용량 관리를 위해 라라벨이 자동으로 오래된 파일을 정리) 파일명이
    // laravel-YYYY-MM-DD.log 형태로 매일 바뀌므로, 그중 가장 최근 파일을 찾아서 보여준다.
    private const MAX_BYTES = 200_000;

    public ?string $logContent = null;

    public ?string $logPath = null;

    public function mount(): void
    {
        $this->refresh();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('새로고침')
                ->icon(Heroicon::OutlinedArrowPath)
                ->action('refresh'),
        ];
    }

    public function refresh(): void
    {
        $path = $this->resolveLatestLogPath();
        $this->logPath = $path;
        $this->logContent = $this->readTail($path);
    }

    // daily 채널은 laravel-YYYY-MM-DD.log 형태로 매일 새 파일을 만든다. 파일명이 날짜순으로 정렬되므로
    // 가장 마지막(최신) 파일을 찾아서 보여준다. 아직 daily 파일이 하나도 없다면(전환 직후 등)
    // 예전 single 채널이 쓰던 laravel.log로 안전하게 대체한다.
    private function resolveLatestLogPath(): string
    {
        $dailyFiles = glob(storage_path('logs/laravel-*.log')) ?: [];

        if (! empty($dailyFiles)) {
            sort($dailyFiles);

            return end($dailyFiles);
        }

        return storage_path('logs/laravel.log');
    }

    // 로그 파일이 커질 수 있으므로 파일 전체를 메모리에 올리지 않고 끝부분만 읽는다.
    // 최신 내용이 위에 오도록 줄 단위로 뒤집어서 보여준다.
    private function readTail(string $path): string
    {
        if (! is_file($path)) {
            return '(아직 로그 파일이 없습니다. 에러가 발생하면 여기에 표시됩니다.)';
        }

        $size = filesize($path);
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return '(로그 파일을 읽을 수 없습니다.)';
        }

        if ($size > self::MAX_BYTES) {
            fseek($handle, -self::MAX_BYTES, SEEK_END);
            fgets($handle); // 잘렸을 수 있는 첫 줄은 버림
        }

        $content = stream_get_contents($handle);
        fclose($handle);

        if (trim((string) $content) === '') {
            return '(로그 내용이 비어 있습니다.)';
        }

        // 로그 항목은 "[2026-01-01 00:00:00] local.ERROR: ..." 형식으로 시작하고, 스택트레이스 등
        // 여러 줄에 걸쳐 이어질 수 있다. 줄 단위가 아니라 항목(entry) 단위로 나눠 최신 항목이 위로 오게 뒤집는다.
        $entries = preg_split('/(?=^\[\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2})/m', trim((string) $content));
        $entries = array_filter($entries, fn (string $entry) => trim($entry) !== '');

        return implode("\n", array_reverse($entries));
    }
}
