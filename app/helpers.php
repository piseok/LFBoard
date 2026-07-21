<?php

use App\Models\Language;

if (! function_exists('front_route')) {
    /**
     * 프론트 라우트(홈/게시판/1:1상담/마이페이지/로그인 등)는 언어별로 이름이 다르게 등록되어 있다
     * (기본 언어는 'board.index', 그 외 언어는 'ja.board.index'처럼 접두사가 붙음 — routes/web.php 참고).
     * 이 헬퍼로 현재 언어에 맞는 라우트를 항상 올바르게 생성한다. 관리자 패널 라우트는 언어 개념이
     * 없으므로 이 헬퍼를 쓰지 않고 route()를 그대로 쓴다.
     */
    function front_route(string $name, mixed $parameters = [], bool $absolute = true): string
    {
        return route(Language::routeNamePrefix().$name, $parameters, $absolute);
    }
}

if (! function_exists('local_datetime')) {
    /**
     * DB에 저장된 시각(항상 config('app.timezone') 기준)을 방문자의 현재 언어에 설정된 시간대로
     * 변환한 Carbon 인스턴스를 반환한다(16번 항목). 원본을 바꾸지 않도록 clone해서 반환하므로
     * `{{ local_datetime($post->created_at)->format('Y-m-d H:i') }}`처럼 기존 ->format() 자리에
     * 그대로 끼워 넣으면 된다. null이 오면 null을 그대로 돌려준다(옵셔널 날짜 필드 대응).
     */
    function local_datetime(?\Illuminate\Support\Carbon $datetime, ?string $locale = null): ?\Illuminate\Support\Carbon
    {
        return $datetime?->clone()->setTimezone(Language::timezoneForLocale($locale));
    }
}
