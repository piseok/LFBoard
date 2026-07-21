<?php

namespace App\Services;

class PhoneCountryService
{
    // 로그인 언어별 기본 선택 국가. 목록에 없는 언어는 한국(KR)으로 대체.
    private const DEFAULT_BY_LOCALE = [
        'ko' => 'KR',
        'en' => 'US',
        'ja' => 'JP',
    ];

    /**
     * @return array<int, array{code: string, dial: string, name: string}>
     */
    public function options(): array
    {
        $dialCodes = require config_path('phone_countries.php');
        $names = require base_path('database/geoip/country-names.php');

        $options = [];
        foreach ($dialCodes as $code => $dial) {
            $options[] = ['code' => $code, 'dial' => $dial, 'name' => $names[$code] ?? $code];
        }

        usort($options, fn ($a, $b) => $a['name'] <=> $b['name']);

        return $options;
    }

    public function defaultCode(?string $locale = null): string
    {
        return self::DEFAULT_BY_LOCALE[$locale ?? app()->getLocale()] ?? 'KR';
    }

    public function dialCodeFor(string $countryCode): ?string
    {
        $dialCodes = require config_path('phone_countries.php');

        return $dialCodes[$countryCode] ?? null;
    }

    // 한국(KR)은 기존 형식(010-1234-5678)을 그대로 유지해야 국내 SMS사(알리고/coolsms)와
    // 기존 화면 표시가 안 깨진다. 그 외 국가는 Twilio 등 해외 발송용으로 E.164 형태(+국가코드...)로
    // 정규화한다. 사용자가 앞자리 0(국내 트렁크 프리픽스)을 입력해도 자동으로 제거한다.
    public function normalize(?string $countryCode, ?string $phone): ?string
    {
        if (blank($phone)) {
            return null;
        }

        $countryCode ??= 'KR';

        if ($countryCode === 'KR') {
            return trim($phone);
        }

        $dial = $this->dialCodeFor($countryCode);

        if ($dial === null) {
            return trim($phone);
        }

        $digits = ltrim(preg_replace('/\D/', '', $phone), '0');

        return "+{$dial}{$digits}";
    }
}
