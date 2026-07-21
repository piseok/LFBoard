<?php

namespace App\Services;

use App\Models\VendorNotice;
use Illuminate\Support\Facades\Http;
use Throwable;

// 관리업체(모회사)가 운영하는 중앙 공지사항 API를 폴링해 로컬에 캐시하는 서비스.
// "유지보수 리포트"(MaintenanceReportResource)가 사이트 → 관리업체로 보고를 보내는 것과
// 반대 방향 — 관리업체 → 사이트로 공지를 받아오는 역할이다. 기대하는 응답 형식은
// SiteSettings::vendorNoticeTab()의 안내 문구 참고 (전역 규약: {"notices":[{"id","title","url","published_at"}, ...]}).
class VendorNoticeSyncService
{
    /**
     * @return int 새로 추가된 공지 수 (조회수 아님)
     */
    public function sync(): int
    {
        $settings = app(SiteSettingService::class);

        if ($settings->get('vendor_notice_enabled') !== '1') {
            return 0;
        }

        $url = $settings->get('vendor_notice_api_url');
        if (empty($url)) {
            return 0;
        }

        try {
            $response = Http::timeout(10)->withToken((string) $settings->get('vendor_notice_api_token'))->get($url);
        } catch (Throwable) {
            return 0;
        }

        if (! $response->successful()) {
            return 0;
        }

        $items = $response->json('notices') ?? $response->json() ?? [];
        if (! is_array($items)) {
            return 0;
        }

        $newCount = 0;

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $externalId = (string) ($item['id'] ?? $item['external_id'] ?? '');
            $title = (string) ($item['title'] ?? '');
            if ($externalId === '' || $title === '') {
                continue;
            }

            $notice = VendorNotice::updateOrCreate(
                ['external_id' => $externalId],
                [
                    'title' => $title,
                    'url' => $item['url'] ?? null,
                    'published_at' => $item['published_at'] ?? null,
                ]
            );

            if ($notice->wasRecentlyCreated) {
                $newCount++;
            }
        }

        return $newCount;
    }
}
