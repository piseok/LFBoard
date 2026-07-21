<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Throwable;

class CaptchaService
{
    private const ENDPOINTS = [
        'recaptcha_v3' => 'https://www.google.com/recaptcha/api/siteverify',
        'hcaptcha' => 'https://hcaptcha.com/siteverify',
        'turnstile' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
    ];

    private const SIMPLE_MATH_SESSION_KEY = 'captcha_math_answer';

    public function __construct(private readonly SiteSettingService $siteSettings) {}

    // 외부 서비스 가입 없이 즉시 쓸 수 있는 자체 수식 인증 문제를 만들어 세션에 정답을 저장하고,
    // 화면에 보여줄 질문 문자열을 반환한다. 새로고침/재요청 시마다 새 문제로 갱신된다.
    public function generateMathChallenge(): string
    {
        $a = random_int(1, 9);
        $b = random_int(1, 9);

        Session::put(self::SIMPLE_MATH_SESSION_KEY, $a + $b);

        return "{$a} + {$b} = ?";
    }

    public function verify(string $token): bool
    {
        $provider = $this->siteSettings->get('captcha_provider');

        if (empty($provider)) {
            return true;
        }

        if ($provider === 'simple_math') {
            return $this->verifySimpleMath($token);
        }

        if (! isset(self::ENDPOINTS[$provider]) || empty($token)) {
            return false;
        }

        $secretKey = $this->siteSettings->get('captcha_secret_key');
        if (empty($secretKey)) {
            return false;
        }

        try {
            $response = Http::asForm()->post(self::ENDPOINTS[$provider], [
                'secret' => $secretKey,
                'response' => $token,
            ]);

            return (bool) ($response->json('success') ?? false);
        } catch (Throwable) {
            return false;
        }
    }

    // 재사용(리플레이) 공격을 막기 위해 정답 확인 후 세션 값을 즉시 제거한다(1회용).
    private function verifySimpleMath(string $token): bool
    {
        $expected = Session::get(self::SIMPLE_MATH_SESSION_KEY);
        Session::forget(self::SIMPLE_MATH_SESSION_KEY);

        if ($expected === null || trim($token) === '') {
            return false;
        }

        return (int) trim($token) === (int) $expected;
    }
}
