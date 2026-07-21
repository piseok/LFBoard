<?php

namespace App\Filament\Resources\Inquiries\Pages;

use App\Filament\Concerns\CancelsToListPage;
use App\Filament\Concerns\HasAiFormFill;
use App\Filament\Resources\Inquiries\InquiryResource;
use App\Services\EmailService;
use App\Services\SiteSettingService;
use App\Services\SmsService;
use Filament\Resources\Pages\EditRecord;

class EditInquiry extends EditRecord
{
    use CancelsToListPage;
    use HasAiFormFill;

    protected static string $resource = InquiryResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    // 처음으로 답변이 등록되는 시점(admin_reply가 채워지고 아직 replied_at이 없던 경우)에만
    // inquiry_reply 메일을 발송한다. 이후 재수정 시에는 중복 발송하지 않는다.
    protected function afterSave(): void
    {
        $record = $this->record;

        if (filled($record->admin_reply) && $record->getOriginal('replied_at') === null) {
            $record->update(['replied_at' => now()]);

            if ($record->email) {
                app(EmailService::class)->send('inquiry_reply', $record->email, [
                    'inquiry_name' => $record->name,
                    'inquiry_title' => $record->title,
                    'reply_content' => $record->admin_reply,
                ], $record->locale);
            }

            if ($record->phone && app(SiteSettingService::class)->get('sms_enabled') === '1') {
                app(SmsService::class)->send(
                    $record->phone,
                    "[답변완료] '{$record->title}' 문의에 답변이 등록되었습니다. 홈페이지에서 확인해 주세요."
                );
            }
        }
    }
}
