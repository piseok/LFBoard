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
}
