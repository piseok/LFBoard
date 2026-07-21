<?php

namespace App\Services;

use App\Models\IpCountryRange;

// 로그인마다 외부 API를 호출하지 않고, 미리 내려받아 둔 정적 IP 대역 데이터를 로컬 DB에서 조회한다
// (db-ip.com IP to Country Lite, CC BY 4.0, 가입/키 불필요 — database/geoip/, `php artisan geoip:import`로 반영).
// 크론이 없는 환경이라 자동 갱신은 하지 않으며, 대역 데이터는 시간이 지나면 조금씩 정확도가 떨어지므로
// 관리자가 가끔 최신 CSV를 받아 재실행하면 된다. IPv4만 지원(IPv6은 사설 IP와 동일하게 조용히 건너뜀).
class GeoIpService
{
    /** @var array<string, string>|null */
    private static ?array $countryNames = null;

    /**
     * @return array{code: string, name: string}|null
     */
    public function lookup(string $ip): ?array
    {
        // 사설/루프백/예약 IP(로컬 개발, 사내망 등)나 IPv6(이번 범위 미지원)은 조회를 건너뛴다.
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return null;
        }

        $ipLong = ip2long($ip);

        if ($ipLong === false) {
            return null;
        }

        $range = IpCountryRange::query()
            ->where('ip_start', '<=', $ipLong)
            ->orderByDesc('ip_start')
            ->first();

        if (! $range || $range->ip_end < $ipLong) {
            return null;
        }

        return [
            'code' => $range->country_code,
            'name' => $this->countryName($range->country_code),
        ];
    }

    private function countryName(string $code): string
    {
        self::$countryNames ??= require base_path('database/geoip/country-names.php');

        return self::$countryNames[$code] ?? $code;
    }
}
