<?php

namespace App\Filament\Resources\VendorNotices\Pages;

use App\Filament\Resources\VendorNotices\VendorNoticeResource;
use App\Services\VendorNoticeSyncService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListVendorNotices extends ListRecords
{
    protected static string $resource = VendorNoticeResource::class;

    public function mount(): void
    {
        parent::mount();

        VendorNoticeResource::markSeenForCurrentUser();
    }

    public function getSubheading(): string
    {
        return '관리업체(모회사)가 배포하는 공지사항입니다. 사이트 설정 > 관리업체 공지사항에서 API를 연동하면 관리자 접속 시 자동으로 갱신됩니다.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncNow')
                ->label('지금 동기화')
                ->icon(Heroicon::OutlinedArrowPath)
                ->action(function (): void {
                    $newCount = app(VendorNoticeSyncService::class)->sync();

                    VendorNoticeResource::markSeenForCurrentUser();

                    Notification::make()
                        ->title($newCount > 0 ? "새 공지 {$newCount}건을 가져왔습니다." : '새로운 공지가 없습니다.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
