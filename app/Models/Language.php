<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Language extends Model
{
    protected $fillable = [
        'code',
        'name',
        'timezone',
        'is_default',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    // 기본 언어는 항상 하나뿐이어야 하므로, 저장 시 is_default=true면 나머지는 자동으로 해제한다.
    // DetectLocale 미들웨어가 활성 언어 목록을 캐시하므로, 변경 시마다 캐시를 비워 즉시 반영되게 한다.
    protected static function booted(): void
    {
        // 기본 언어를 비활성화한 채로 두면 접두사 없는 라우트는 그대로 살아있는데 hreflang의
        // x-default/사이트맵 인덱스/언어 전환 버튼에서는 빠져버리는 앞뒤가 안 맞는 상태가 됨
        // (2026-07-05 다국어 ON/OFF 점검 중 발견) — 기본 언어는 항상 활성 상태를 강제한다.
        static::saving(function (self $language): void {
            if ($language->is_default) {
                $language->is_active = true;
            }
        });

        static::saved(function (self $language): void {
            if ($language->is_default) {
                static::query()->where('id', '!=', $language->id)->where('is_default', true)->update(['is_default' => false]);
            }

            Cache::forget('languages.active');
            Cache::forget("languages.timezone.{$language->code}");
        });

        static::deleted(function (): void {
            Cache::forget('languages.active');
        });
    }

    public static function defaultCode(): string
    {
        return static::query()->where('is_default', true)->value('code') ?? 'ko';
    }

    /**
     * @return array<int, string>
     */
    public static function activeNonDefaultCodes(): array
    {
        return static::query()->where('is_active', true)->where('is_default', false)->pluck('code')->all();
    }

    // routes/web.php가 기본 언어는 접두사 없이, 나머지는 "{코드}."로 라우트 이름을 등록해두므로
    // (예: board.index vs ja.board.index), 언어별로 올바른 라우트를 참조하려면 이 접두사가 필요하다.
    public static function routeNamePrefix(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $locale === self::defaultCode() ? '' : "{$locale}.";
    }

    // 방문자 화면에 날짜/시간을 표시할 때 쓸 시간대(16번 항목) — DB 저장값 자체는 항상
    // config('app.timezone')이고, 화면 표시 시점에만 이 값으로 변환한다(local_datetime() 헬퍼 참고).
    public static function timezoneForLocale(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return Cache::remember(
            "languages.timezone.{$locale}",
            3600,
            fn () => static::query()->where('code', $locale)->value('timezone') ?? config('app.timezone')
        );
    }
}
