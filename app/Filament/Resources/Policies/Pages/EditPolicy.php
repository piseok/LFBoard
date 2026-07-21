<?php

namespace App\Filament\Resources\Policies\Pages;

use App\Filament\Concerns\CancelsToListPage;
use App\Filament\Resources\Policies\PolicyResource;
use App\Services\PolicyChangeNoticeService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPolicy extends EditRecord
{
    use CancelsToListPage;

    protected static string $resource = PolicyResource::class;

    private bool $shouldSendNotice = false;

    private ?string $noticeSubject = null;

    private ?string $noticeMessage = null;

    private ?string $originalContent = null;

    protected function getHeaderActions(): array
    {
        return [];
    }

    // send_notice/notice_subject/notice_message은 Policy 모델의 실제 컬럼이 아니라, 저장 시점에만
    // 값을 읽어 메일 발송에 쓰고 $data에서 제거한다. 이 시점에는 아직 레코드가 갱신되기 전이라
    // $this->record->content가 "변경 전" 내용 그대로다(diff 비교에 필요).
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->shouldSendNotice = (bool) ($data['send_notice'] ?? false);
        $this->noticeSubject = $data['notice_subject'] ?? null;
        $this->noticeMessage = $data['notice_message'] ?? null;
        $this->originalContent = $this->record->content;

        unset($data['send_notice'], $data['notice_subject'], $data['notice_message']);

        return $data;
    }

    // 레코드가 실제로 저장된 이후에만 발송한다(저장 실패 시 안내 메일이 나가지 않도록).
    protected function afterSave(): void
    {
        if (! $this->shouldSendNotice) {
            return;
        }

        $result = app(PolicyChangeNoticeService::class)->send(
            $this->record,
            $this->originalContent,
            (string) $this->noticeSubject,
            (string) $this->noticeMessage,
        );

        Notification::make()
            ->title("변경 안내 메일 발송 완료: 성공 {$result['sent']}건, 실패 {$result['failed']}건")
            ->success()
            ->send();
    }
}
