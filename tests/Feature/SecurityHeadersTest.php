<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_standard_security_headers_are_present(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Content-Security-Policy');
    }

    public function test_hsts_is_only_sent_over_https(): void
    {
        $this->get('/')->assertHeaderMissing('Strict-Transport-Security');

        $response = $this->get('https://localhost/');
        $response->assertHeader('Strict-Transport-Security');
    }

    public function test_csp_is_not_forced_on_admin_panel(): void
    {
        // Filament/Livewire/Alpine이 인라인 스크립트에 크게 의존해 CSP를 걸면 관리자 화면
        // 자체가 깨지므로 의도적으로 제외되어 있다 — 실수로 다시 켜지는지 회귀 확인.
        $response = $this->get('/admin/login');

        $response->assertHeaderMissing('Content-Security-Policy');
    }

    public function test_csp_allows_kakao_map_and_youtube_frames(): void
    {
        // 관리자가 오시는길 등에 붙여넣는 카카오맵/유튜브 iframe(2026-09-22) — HtmlSanitizerService의
        // URI.SafeIframeRegexp와 반드시 같은 출처를 허용해야 한다(한쪽만 넓히면 여전히 막힘).
        $csp = $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString('frame-src', $csp);
        $this->assertStringContainsString('https://www.youtube.com', $csp);
        $this->assertStringContainsString('https://www.youtube-nocookie.com', $csp);
        $this->assertStringContainsString('https://map.kakao.com', $csp);
    }
}
