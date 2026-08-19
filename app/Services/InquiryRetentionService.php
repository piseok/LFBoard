<?php

namespace App\Services;

use App\Models\Inquiry;

// 1:1 문의는 이름/이메일/전화 등 개인정보를 수집하면서도(게시판 본인인증 동의문구와 달리) 실제
// 파기 로직이 없었다 — AiChatService::pruneExpired()와 동일한 패턴으로 보유기간이 지난 문의를
// 완전히 삭제한다(소프트삭제 여부 무관). PruneInquiries 미들웨어가 하루 1회만 호출한다.
class InquiryRetentionService
{
    public function __construct(
        private readonly SiteSettingService $siteSettings,
        private readonly UploadService $uploadService,
    ) {}

    public function pruneExpired(): void
    {
        $months = (int) $this->siteSettings->get('inquiry_retention_months', '36');

        if ($months <= 0) {
            return;
        }

        $expired = Inquiry::withTrashed()
            ->where('created_at', '<', now()->subMonths($months))
            ->get();

        if ($expired->isEmpty()) {
            return;
        }

        $expired->filter(fn (Inquiry $inquiry) => $inquiry->file_path)
            ->each(fn (Inquiry $inquiry) => $this->uploadService->delete($inquiry->file_path));

        Inquiry::withTrashed()->whereIn('id', $expired->pluck('id'))->forceDelete();
    }
}
