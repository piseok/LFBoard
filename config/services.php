<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // AI 비서. API 키는 .env 가 아니라 관리자 > 사이트 설정 > AI 연동 탭(SiteSettings)에서 관리한다.
    // fake_provider: 개발용 가짜 제공자(유료 API 를 부르지 않고 도구 호출·카드·한도 흐름을 브라우저로 확인)를 목록에 넣을지.
    // APP_ENV=local 이고 이 값이 true 일 때만 켜진다(AiChatService::fakeProviderEnabled) - 환경 이름 하나만으로는 켜지지 않는다.
    'ai' => [
        'fake_provider' => filter_var(env('AI_FAKE_PROVIDER', false), FILTER_VALIDATE_BOOLEAN),
    ],

    // 소셜 로그인(google/kakao/naver) 설정은 .env가 아니라 관리자 > 사이트 설정 > 소셜 로그인 탭(SiteSettings)에서
    // 관리하며, App\Http\Controllers\Auth\SocialLoginController가 요청 시점에 이 배열에 값을 주입한다.

];
