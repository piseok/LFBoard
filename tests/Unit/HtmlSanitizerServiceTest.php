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
}
