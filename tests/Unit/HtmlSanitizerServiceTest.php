<?php

namespace Tests\Unit;

use App\Services\HtmlSanitizerService;
use Tests\TestCase;

/**
 * 에디터에서 링크 "새창"(target="_blank")을 선택해도 저장 후 실제로는 새창으로 안 열리던 버그
 * (2026-09-22) 회귀 테스트. HTML.Allowed에 a[target]을 넣는 것만으로는 부족하고
 * Attr.AllowedFrameTargets(기본값 빈 배열)를 별도로 채워야 한다 — HtmlSanitizerService 주석 참고.
 */
class HtmlSanitizerServiceTest extends TestCase
{
    public function test_target_blank_survives_sanitization(): void
    {
        $sanitizer = new HtmlSanitizerService();

        $result = $sanitizer->clean('<p><a href="https://example.com" target="_blank" rel="noopener">링크</a></p>');

        $this->assertStringContainsString('target="_blank"', $result);
    }

    public function test_disallowed_target_values_are_stripped(): void
    {
        $sanitizer = new HtmlSanitizerService();

        $result = $sanitizer->clean('<p><a href="https://example.com" target="_top">링크</a></p>');

        $this->assertStringNotContainsString('target=', $result);
    }

    public function test_youtube_and_kakao_map_iframes_survive_sanitization(): void
    {
        // 오시는길 페이지 등에 관리자가 직접 붙여넣는 embed 코드(2026-09-22) — 저장 단계에서
        // 지워지면 CSP를 아무리 풀어도 소용없다.
        $sanitizer = new HtmlSanitizerService();

        $youtube = $sanitizer->clean('<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ" width="560" height="315"></iframe>');
        $this->assertStringContainsString('<iframe', $youtube);
        $this->assertStringContainsString('youtube.com/embed/dQw4w9WgXcQ', $youtube);

        $kakao = $sanitizer->clean('<iframe src="https://map.kakao.com/scripts/eventListener.html" width="560" height="315"></iframe>');
        $this->assertStringContainsString('<iframe', $kakao);
        $this->assertStringContainsString('map.kakao.com', $kakao);
    }

    public function test_iframes_from_untrusted_origins_lose_their_src(): void
    {
        // HTMLPurifier는 URI.SafeIframeRegexp에 안 맞는 src를 만나면 iframe 태그 자체가 아니라
        // src 속성만 지운다(a[href]가 잘못된 URI를 만났을 때와 동일한 처리 방식) — 실측 확인
        // (2026-09-22). src 없는 iframe은 about:blank라 안전하므로, 중요한 건 공격자 origin
        // 문자열이 결과 어디에도 안 남는 것.
        $sanitizer = new HtmlSanitizerService();

        $result = $sanitizer->clean('<iframe src="https://evil.example.com/phishing" width="560" height="315"></iframe>');

        $this->assertStringNotContainsString('evil.example.com', $result);
    }
}
