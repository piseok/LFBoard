<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    protected $fillable = [
        'type',
        'locale',
        'name',
        'subject',
        'body',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    // 요청한 언어 버전이 없으면 영어로, 그마저도 없으면 기본 언어(한국어)로 폴백한다(사용자 지시 —
    // "한국어 제외하면 전부 영어로" — 언어가 늘어나도 영어 버전 하나만 있으면 전부 커버되므로
    // 새 언어가 추가될 때마다 번역을 늘리지 않아도 됨). welcome/email_verification/password_reset/
    // login_country_changed처럼 모든 언어권에서 쓰이는 타입만 한국어+영어 두 버전이 있고,
    // dormant_notice처럼 한국어 전용 정책 메일은 'ko' 한 행뿐이다.
    public static function findByType(string $type, ?string $locale = null): ?self
    {
        $locale ??= Language::defaultCode();

        return static::where('type', $type)->where('locale', $locale)->first()
            ?? static::where('type', $type)->where('locale', 'en')->first()
            ?? static::where('type', $type)->where('locale', Language::defaultCode())->first();
    }
}
