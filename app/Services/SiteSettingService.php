<?php

namespace App\Services;

use App\Models\Language;
use App\Models\SiteSetting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class SiteSettingService
{
    private const CACHE_KEY = 'site_settings.all';

    // 유출 시 제3자 서비스 접근으로 이어지는 값들 — SiteSettings 폼에서도 ->password()로
    // 마스킹해 이미 "민감함"으로 취급하고 있는 것과 동일한 기준으로 골랐다. DB에 평문으로
    // 남아있으면 백업 파일이나 DB 접근 권한이 새는 것만으로도 SMTP/소셜로그인/AI/문자 발송
    // 계정이 함께 털릴 수 있어 저장 시 암호화한다.
    private const ENCRYPTED_KEYS = [
        'captcha_secret_key',
        'social_google_client_secret',
        'social_kakao_client_secret',
        'social_naver_client_secret',
        'mail_password',
        'sms_api_secret',
        'identity_verification_api_key',
        'ai_openai_api_key',
        'ai_gemini_api_key',
        'maintenance_report_token',
        'vendor_notice_api_token',
    ];

    public function get(string $key, ?string $default = null): ?string
    {
        return $this->all()[$key] ?? $default;
    }

    /**
     * `inquiry_categories`는 언어별로 다른 목록을 가질 수 있어 {"ko":[...],"en":[...]} 형태의
     * JSON 객체로 저장된다(SiteSettings::inquiryTab() 참고). 요청 언어에 목록이 없으면 기본
     * 언어(한국어) 목록으로 자동 대체한다.
     *
     * @return array<int, string>
     */
    public function getInquiryCategories(?string $locale = null): array
    {
        $locale ??= app()->getLocale();
        $byLocale = json_decode($this->get('inquiry_categories', '{}'), true) ?: [];

        return ($byLocale[$locale] ?? null) ?: ($byLocale[Language::defaultCode()] ?? []);
    }

    /**
     * 언어별로 다른 값을 가질 수 있는 설정(사이트명/푸터 저작권 문구 등)을 읽는다. 값이
     * {"ko":"...","en":"..."} 형태의 JSON 객체면 요청 언어(없으면 기본 언어)로 대체하고,
     * 아직 언어별로 분리되기 전(레거시 단일 문자열)이면 그 값을 모든 언어에 그대로 사용한다.
     */
    public function getLocalized(string $key, ?string $locale = null, ?string $default = null): ?string
    {
        $raw = $this->get($key);

        if ($raw === null || $raw === '') {
            return $default;
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return $raw;
        }

        $locale ??= app()->getLocale();

        return ($decoded[$locale] ?? null) ?: ($decoded[Language::defaultCode()] ?? $default);
    }

    public function set(string $key, ?string $value, string $group = 'general'): void
    {
        if (in_array($key, self::ENCRYPTED_KEYS, true) && filled($value)) {
            $value = Crypt::encryptString($value);
        }

        SiteSetting::updateOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);
        Cache::forget(self::CACHE_KEY);
    }

    public function all(): array
    {
        $settings = Cache::remember(self::CACHE_KEY, 3600, function () {
            return SiteSetting::query()->pluck('value', 'key')->all();
        });

        foreach (self::ENCRYPTED_KEYS as $key) {
            if (filled($settings[$key] ?? null)) {
                $settings[$key] = $this->decryptLegacySafe($settings[$key]);
            }
        }

        return $settings;
    }

    // 이 암호화를 붙이기 전에 평문으로 저장된 기존 값들도 있으므로, 복호화에 실패하면(=암호화된
    // 적 없는 레거시 평문) 원래 값을 그대로 돌려준다 — 별도 마이그레이션 없이도 다음에 그 설정을
    // 저장하는 순간 자동으로 암호화된 값으로 바뀐다.
    private function decryptLegacySafe(string $value): string
    {
        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return $value;
        }
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
