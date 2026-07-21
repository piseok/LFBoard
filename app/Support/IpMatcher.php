<?php

namespace App\Support;

class IpMatcher
{
    // 단일 IP 또는 CIDR 표기(예: 123.45.67.0/24)를 지원한다. IPv6 대역(CIDR)은 지원하지 않고 단일 주소 일치만 확인한다.
    // 관리자 IP 허용목록, 사이트 접속 차단목록 등 IP 매칭이 필요한 여러 곳에서 공용으로 사용한다.
    public static function matches(string $ip, string $entry): bool
    {
        if ($entry === '') {
            return false;
        }

        if (! str_contains($entry, '/')) {
            return $ip === $entry;
        }

        [$subnet, $bits] = explode('/', $entry, 2);

        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            || ! filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            || ! is_numeric($bits)
        ) {
            return false;
        }

        $bits = (int) $bits;

        if ($bits < 0 || $bits > 32) {
            return false;
        }

        $mask = $bits === 0 ? 0 : (-1 << (32 - $bits));

        return (ip2long($ip) & $mask) === (ip2long($subnet) & $mask);
    }

    public static function matchesAny(string $ip, array $entries): bool
    {
        foreach ($entries as $entry) {
            if (self::matches($ip, trim((string) $entry))) {
                return true;
            }
        }

        return false;
    }
}
