<?php

namespace App\Services;

use App\Models\Language;
use App\Models\User;
use App\Models\UserLoginCountry;

// 로그인 성공 시(일반 로그인 + 소셜 로그인 둘 다) IP로 국가를 조회해, 이 계정에서 처음 보는
// 국가면 보안 알림을 1회만 발송한다(이후 같은 국가에서 다시 로그인해도 재발송하지 않음).
// VPN 등으로 국가가 실제와 다르게 판별될 수 있는 것은 감수하기로 사용자 승인됨.
class LoginCountryAlertService
{
    public function __construct(
        private readonly SiteSettingService $siteSettings,
        private readonly GeoIpService $geoIp,
        private readonly EmailService $emailService,
        private readonly SmsService $smsService,
        private readonly KakaoAlimTalkService $kakaoService,
    ) {}

    public function handleLogin(User $user, string $ip): void
    {
        if ($this->siteSettings->get('login_country_alert_enabled') !== '1') {
            return;
        }

        $location = $this->geoIp->lookup($ip);

        if (! $location) {
            return;
        }

        $alreadyKnown = UserLoginCountry::query()
            ->where('user_id', $user->id)
            ->where('country_code', $location['code'])
            ->exists();

        if ($alreadyKnown) {
            return;
        }

        UserLoginCountry::create([
            'user_id' => $user->id,
            'country_code' => $location['code'],
            'country_name' => $location['name'],
            'first_seen_at' => now(),
        ]);

        $this->sendAlert($user, $location);
    }

    /**
     * @param  array{code: string, name: string}  $location
     */
    private function sendAlert(User $user, array $location): void
    {
        $trustDays = (int) $this->siteSettings->get('login_country_trust_days', '7');

        $variables = [
            'user_name' => $user->name,
            'country_name' => $location['name'],
            'trust_days' => (string) $trustDays,
            'reset_url' => route(Language::routeNamePrefix($user->locale).'password.request'),
        ];

        if ($this->siteSettings->get('email_login_country_changed_enabled') === '1' && filled($user->email)) {
            $this->emailService->send('login_country_changed', $user->email, $variables, $user->locale);
        }

        if (blank($user->phone)) {
            return;
        }

        $message = "[보안 알림] 새로운 국가({$location['name']})에서 로그인이 감지되었습니다. 본인이 아니라면 비밀번호를 변경해 주세요.";

        if ($this->siteSettings->get('login_country_alert_sms_enabled') === '1') {
            $this->smsService->send($user->phone, $message);
        }

        // 카카오 알림톡은 카카오톡 계정이 있어야 하는 한국 전용 채널이라, 선호 언어가 한국어인
        // 회원에게만 발송한다(사용자 지시).
        if ($this->siteSettings->get('login_country_alert_kakao_enabled') === '1' && $user->locale === 'ko') {
            $this->kakaoService->send($user->phone, 'login_country_changed', $message, $message);
        }
    }
}
