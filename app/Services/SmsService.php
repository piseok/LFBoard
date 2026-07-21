<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SmsService
{
    public function __construct(private readonly SiteSettingService $siteSettings) {}

    public function isEnabled(): bool
    {
        return filled($this->siteSettings->get('sms_provider'))
            && filled($this->siteSettings->get('sms_api_key'))
            && filled($this->siteSettings->get('sms_from'));
    }

    // $message는 90byte(45자 내외) 초과 시 공급사가 자동으로 LMS(장문)로 전환한다.
    public function send(string $to, string $message): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        return match ($this->siteSettings->get('sms_provider')) {
            'aligo' => $this->sendViaAligo($to, $message),
            'coolsms' => $this->sendViaCoolsms($to, $message),
            'twilio' => $this->sendViaTwilio($to, $message),
            default => false,
        };
    }

    // https://smartsms.aligo.in/admin/api/spec.html — key/userid 인증, multipart/form-data.
    private function sendViaAligo(string $to, string $message): bool
    {
        try {
            $response = Http::asForm()->post('https://apis.aligo.in/send/', [
                'key' => $this->siteSettings->get('sms_api_key'),
                'user_id' => $this->siteSettings->get('sms_api_secret'),
                'sender' => $this->siteSettings->get('sms_from'),
                'receiver' => $to,
                'msg' => $message,
            ]);

            return (int) ($response->json('result_code') ?? -1) === 1;
        } catch (\Throwable) {
            return false;
        }
    }

    // https://docs.coolsms.co.kr (Solapi) v4 — HMAC-SHA256 서명 인증.
    private function sendViaCoolsms(string $to, string $message): bool
    {
        try {
            $apiKey = (string) $this->siteSettings->get('sms_api_key');
            $apiSecret = (string) $this->siteSettings->get('sms_api_secret');
            $date = now()->toIso8601String();
            $salt = Str::random(32);
            $signature = hash_hmac('sha256', $date.$salt, $apiSecret);

            $response = Http::withHeaders([
                'Authorization' => "HMAC-SHA256 apiKey={$apiKey}, date={$date}, salt={$salt}, signature={$signature}",
            ])->post('https://api.coolsms.co.kr/messages/v4/send', [
                'message' => [
                    'to' => $to,
                    'from' => $this->siteSettings->get('sms_from'),
                    'text' => $message,
                ],
            ]);

            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    // https://www.twilio.com/docs/sms/send-messages — 국내 통신사 전용인 알리고/coolsms와 달리
    // 해외 번호도 발송 가능(국내/해외 혼용 지원). sms_api_key=Account SID, sms_api_secret=Auth Token,
    // sms_from=Twilio에서 발급받은 발신번호(+국가코드 포함, 예: +15551234567).
    private function sendViaTwilio(string $to, string $message): bool
    {
        try {
            $accountSid = (string) $this->siteSettings->get('sms_api_key');
            $authToken = (string) $this->siteSettings->get('sms_api_secret');

            $response = Http::asForm()
                ->withBasicAuth($accountSid, $authToken)
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json", [
                    'To' => $to,
                    'From' => $this->siteSettings->get('sms_from'),
                    'Body' => $message,
                ]);

            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }
}
