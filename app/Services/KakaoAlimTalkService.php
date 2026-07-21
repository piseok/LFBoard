<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

// 카카오 알림톡은 발신 프로필(sender key)과 템플릿(사전 승인 필요)이 있어야 실제 발송이 가능하다.
// Aligo 계정 하나로 SMS(SmsService)와 알림톡을 함께 사용할 수 있어 별도 업체 계약 없이도 시작할 수 있다.
class KakaoAlimTalkService
{
    public function __construct(private readonly SiteSettingService $siteSettings) {}

    public function isEnabled(): bool
    {
        return $this->siteSettings->get('kakao_provider') === 'aligo'
            && filled($this->siteSettings->get('kakao_api_key'))
            && filled($this->siteSettings->get('kakao_sender_key'));
    }

    // $fallbackMessage를 주면 알림톡 실패(수신거부/미가입 등) 시 문자로 자동 대체 발송된다(failover).
    public function send(string $to, string $templateCode, string $message, ?string $fallbackMessage = null): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        try {
            $params = [
                'apikey' => $this->siteSettings->get('kakao_api_key'),
                'userid' => $this->siteSettings->get('sms_api_secret'),
                'senderkey' => $this->siteSettings->get('kakao_sender_key'),
                'tpl_code' => $templateCode,
                'sender' => $this->siteSettings->get('sms_from'),
                'receiver_1' => $to,
                'subject_1' => '알림',
                'message_1' => $message,
            ];

            if (filled($fallbackMessage)) {
                $params['failover'] = 'Y';
                $params['fsubject_1'] = '알림';
                $params['fmessage_1'] = $fallbackMessage;
            }

            $response = Http::asForm()->post('https://kakaoapi.aligo.in/akv10/alimtalk/send/', $params);

            return (int) ($response->json('code') ?? -1) === 0;
        } catch (\Throwable) {
            return false;
        }
    }
}
