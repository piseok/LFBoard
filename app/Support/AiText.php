<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

// AI 가 만든 문자열을 서버가 받아들이는 방법을 한 곳에 모은다. 모델 출력은 프롬프트 인젝션으로 얼마든지
// 오염될 수 있으므로 한 줄 칸은 제어문자를 걷어 낸 평문으로, 본문은 "평문(가벼운 마크다운)"으로만 받고 HTML 은
// 서버가 직접 만든다(SafeMarkdown::editorHtml - 원문 HTML 이스케이프, 링크·이미지는 글자만).
// 이렇게 하면 정제기(HtmlSanitizerService)가 외부 <img>/<a> 를 허용하더라도 모델이 추적 이미지나 피싱 링크를
// 공개 화면에 심을 수 없다.
final class AiText
{
    // 제어문자(개행 포함)를 공백으로 바꾸고 연속 공백을 줄여 한 줄로 만든다 - 제목·ALT·메타·감사 로그 라벨용.
    public static function singleLine(mixed $value, int $max): string
    {
        $text = is_scalar($value) ? (string) $value : '';
        $text = preg_replace('/[\p{Cc}\p{Cf}\x{2028}\x{2029}]+/u', ' ', $text) ?? '';
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        return mb_substr($text, 0, $max);
    }

    // 개행은 살리고 나머지 제어문자는 지운다. 3줄 이상 연속 개행은 두 줄로 줄이고 길이를 자른다.
    public static function multiLine(mixed $value, int $max): string
    {
        $text = is_scalar($value) ? (string) $value : '';
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[^\P{Cc}\n\t]+/u', '', $text) ?? '';
        $text = preg_replace('/[\p{Cf}\x{2028}\x{2029}]+/u', '', $text) ?? '';
        $text = trim(preg_replace("/\n{3,}/", "\n\n", $text) ?? '');

        return mb_substr($text, 0, $max);
    }

    // http/https 이고 호스트가 있는 URL 만 통과(javascript:, data:, 상대경로 등은 버린다).
    public static function httpUrl(mixed $value, int $max = 255): ?string
    {
        $url = self::singleLine($value, $max + 1);

        if ($url === '' || mb_strlen($url) > $max || ! preg_match('#^https?://#i', $url)) {
            return null;
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false || blank(parse_url($url, PHP_URL_HOST))) {
            return null;
        }

        return $url;
    }

    /**
     * 정수 인자 - 숫자 문자열도 받되 범위를 벗어나면 null.
     */
    public static function intInRange(mixed $value, int $min, int $max): ?int
    {
        if (is_string($value) && preg_match('/^-?\d+$/', trim($value))) {
            $value = (int) trim($value);
        }

        if (! is_int($value) && ! (is_float($value) && floor($value) === $value)) {
            return null;
        }

        $value = (int) $value;

        return ($value >= $min && $value <= $max) ? $value : null;
    }

    /**
     * 엄격한 날짜(YYYY-MM-DD)만 받는다. 형식이 틀리면 null(조용히 잘못된 날짜가 되는 것을 막는다).
     */
    public static function strictDate(mixed $value): ?CarbonInterface
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($value))) {
            return null;
        }

        $value = trim($value);

        try {
            $date = Carbon::createFromFormat('!Y-m-d', $value);
        } catch (\Throwable) {
            return null;
        }

        // createFromFormat 은 2026-02-31 을 3월 3일로 넘긴다 - 되돌려서 같은지 확인.
        return ($date !== null && $date->format('Y-m-d') === $value) ? $date : null;
    }
}
