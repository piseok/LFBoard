<?php

namespace App\Services;

use Illuminate\Http\Request;

class CookieConsentService
{
    public const COOKIE_NAME = 'cookie_consent';

    public function __construct(
        private readonly SiteSettingService $siteSettings,
        private readonly GeoIpService $geoIp,
    ) {}

    // 배너를 지금 보여줘야 하는지 — 기능 자체가 꺼져 있거나, 이미 동의/거부를 결정했거나,
    // 관리자가 지정한 언어/국가 범위 밖이면 표시하지 않는다.
    public function shouldShow(Request $request): bool
    {
        if ($this->siteSettings->get('cookie_consent_enabled') !== '1') {
            return false;
        }

        if ($request->cookie(self::COOKIE_NAME) !== null) {
            return false;
        }

        $locales = json_decode($this->siteSettings->get('cookie_consent_locales', '[]'), true) ?: [];
        if ($locales && ! in_array(app()->getLocale(), $locales, true)) {
            return false;
        }

        $countries = json_decode($this->siteSettings->get('cookie_consent_countries', '[]'), true) ?: [];
        if ($countries) {
            $country = $this->geoIp->lookup((string) $request->ip());
            // 국가를 판별할 수 없으면(사설 IP, 데이터 없음 등) 안전하게 노출한다 — 관리자가 설정한
            // 국가 제한을 우회당하는 대신, 동의 없이 비필수 쿠키가 나가는 상황을 막는 쪽을 우선한다.
            if ($country && ! in_array($country['code'], $countries, true)) {
                return false;
            }
        }

        return true;
    }

    // Google Analytics 등 비필수 스크립트를 지금 로드해도 되는지 — 배너 기능 자체를 안 쓰면(기존
    // 동작 그대로) 항상 허용, 쓰면 방문자가 명시적으로 동의했을 때만 허용한다.
    public function analyticsAllowed(Request $request): bool
    {
        if ($this->siteSettings->get('cookie_consent_enabled') !== '1') {
            return true;
        }

        return $request->cookie(self::COOKIE_NAME) === 'accepted';
    }

    public function message(): string
    {
        return $this->siteSettings->getLocalized('cookie_consent_message')
            ?: __('이 사이트는 더 나은 서비스 제공을 위해 쿠키를 사용합니다. 계속 이용하시면 쿠키 사용에 동의하는 것으로 간주됩니다.');
    }
}
