<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;

// 사용자 테스트 결함 1("다크 모드에서 AI 비서 위젯의 select·textarea 글자가 안 보임")의 회귀 시험.
// 원인: 예전 위젯은 blade 안 <style> 에 라이트 색(#fff·#f3f4f6 등)만 하드코딩했고 select/textarea 에는 배경·글자색을 지정하지
// 않아, Filament 다크 테마(html.dark)가 입히는 밝은 글자색을 물려받아 흰 배경 위에 흰 글씨가 됐다.
// 고침: public/css/ai-chat.css 가 색을 토큰으로만 쓰고(:root 라이트 + .dark 다크 두 벌), 입력 요소의 배경·글자색·테두리·placeholder 를
// 모두 명시한다. 이 시험은 (1) 구조 규칙(토큰 밖 색 리터럴 없음, 입력 요소가 토큰으로 색을 지정함)과 (2) 두 모드의 대비 수치(WCAG 2.2 AA)를
// 계산해 고정한다. 브라우저에서 실제 computed color 로 확인한 값은 AI_V2_REPORT.md 에 있다.
class AiWidgetCssTest extends TestCase
{
    private string $css;

    protected function setUp(): void
    {
        parent::setUp();
        $this->css = (string) file_get_contents(base_path('public/css/ai-chat.css'));
    }

    /** @return array<string, string> 토큰 이름 → 해석된 #rrggbb(var(--primary-*, #hex) 는 대체값) */
    private function tokens(string $selector): array
    {
        $this->assertSame(1, preg_match('/^'.preg_quote($selector, '/').'\s*\{([^}]*)\}/m', $this->css, $block), "{$selector} 토큰 블록이 있어야 합니다");

        preg_match_all('/--(aic-[a-z-]+)\s*:\s*([^;]+);/', $block[1], $pairs, PREG_SET_ORDER);
        $tokens = [];

        foreach ($pairs as [, $name, $value]) {
            $value = trim($value);

            if (preg_match('/^var\(--[a-z0-9-]+,\s*(#[0-9a-fA-F]{6})\)$/', $value, $fallback)) {
                $value = $fallback[1];
            }

            if (preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
                $tokens[$name] = strtolower($value);
            }
        }

        return $tokens;
    }

    private function luminance(string $hex): float
    {
        $channels = array_map(function (string $part) {
            $v = hexdec($part) / 255;

            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        }, str_split(ltrim($hex, '#'), 2));

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    private function ratio(string $a, string $b): float
    {
        [$l1, $l2] = [$this->luminance($a), $this->luminance($b)];

        return (max($l1, $l2) + 0.05) / (min($l1, $l2) + 0.05);
    }

    public function test_both_modes_define_the_same_tokens(): void
    {
        $light = $this->tokens(':root');
        $dark = $this->tokens('.dark');

        $this->assertNotEmpty($light);
        $this->assertEqualsCanonicalizing(array_keys($light), array_keys($dark), '라이트와 다크는 같은 토큰 집합을 정의해야 합니다');
        $this->assertNotSame($light['aic-bg'], $dark['aic-bg']);
        $this->assertNotSame($light['aic-input-bg'], $dark['aic-input-bg']);
        $this->assertNotSame($light['aic-input-fg'], $dark['aic-input-fg']);
    }

    public function test_text_pairs_meet_wcag_aa_in_light_and_dark(): void
    {
        // [글자 토큰, 배경 토큰, 최소 대비]
        $pairs = [
            ['aic-fg', 'aic-bg', 4.5], ['aic-muted', 'aic-bg', 4.5], ['aic-muted', 'aic-surface', 4.5],
            ['aic-surface-fg', 'aic-surface', 4.5], ['aic-accent-fg', 'aic-accent', 4.5], ['aic-accent-fg', 'aic-accent-hover', 4.5],
            ['aic-soft-fg', 'aic-soft', 4.5], ['aic-fg', 'aic-soft', 4.5],
            ['aic-input-fg', 'aic-input-bg', 4.5], ['aic-placeholder', 'aic-input-bg', 4.5],
            ['aic-link', 'aic-bg', 4.5], ['aic-link', 'aic-surface', 4.5],
            ['aic-danger-fg', 'aic-danger-bg', 4.5], ['aic-ok-fg', 'aic-ok-bg', 4.5], ['aic-warn-fg', 'aic-warn-bg', 4.5],
            ['aic-fg', 'aic-surface', 4.5],
            // 비텍스트(입력칸 테두리·포커스 링·강조 테두리)는 3:1
            ['aic-input-border', 'aic-bg', 3.0], ['aic-input-border', 'aic-input-bg', 3.0],
            ['aic-focus', 'aic-bg', 3.0], ['aic-focus', 'aic-surface', 3.0], ['aic-accent', 'aic-bg', 3.0],
        ];

        foreach (['light' => $this->tokens(':root'), 'dark' => $this->tokens('.dark')] as $mode => $tokens) {
            foreach ($pairs as [$fg, $bg, $min]) {
                $this->assertArrayHasKey($fg, $tokens, "{$mode}: --{$fg} 가 정의되어야 합니다");
                $this->assertArrayHasKey($bg, $tokens, "{$mode}: --{$bg} 가 정의되어야 합니다");
                $ratio = $this->ratio($tokens[$fg], $tokens[$bg]);
                $this->assertGreaterThanOrEqual($min, $ratio, sprintf('%s: --%s(%s) on --%s(%s) 대비 %.2f:1 < %.1f:1', $mode, $fg, $tokens[$fg], $bg, $tokens[$bg], $ratio, $min));
            }
        }
    }

    public function test_no_color_literals_outside_the_two_token_blocks(): void
    {
        // 토큰 블록(:root, .dark)을 지우고 남은 규칙에는 #hex / rgb() / rgba() 색 리터럴이 없어야 한다 - 색은 토큰으로만.
        $rest = preg_replace('/^(:root|\.dark)\s*\{[^}]*\}/m', '', $this->css) ?? '';
        $rest = preg_replace('#/\*.*?\*/#s', '', $rest) ?? '';

        $this->assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{3,8}\b/', $rest, '토큰 밖에 #hex 색이 있습니다');
        $this->assertDoesNotMatchRegularExpression('/\brgba?\(/i', $rest, '토큰 밖에 rgb()/rgba() 색이 있습니다');
        $this->assertDoesNotMatchRegularExpression('/\b(white|black)\b\s*;/i', $rest, '색 이름 리터럴 금지');
    }

    public function test_every_form_control_sets_background_color_and_border_explicitly_from_tokens(): void
    {
        foreach (['.aic-select', '.aic-textarea'] as $selector) {
            $this->assertSame(1, preg_match('/'.preg_quote($selector, '/').',?\s*[^{]*\{([^}]*)\}/', $this->css, $rule), $selector);
        }

        // select/textarea 규칙(두 선택자가 한 규칙을 공유한다)에 배경·글자색·테두리·글자 채움색이 토큰으로 있다.
        $this->assertSame(1, preg_match('/\.aic-select,\s*\.aic-textarea\s*\{([^}]*)\}/', $this->css, $shared));
        foreach (['background: var(--aic-input-bg)', 'color: var(--aic-input-fg)', 'border: 1px solid var(--aic-input-border)', '-webkit-text-fill-color: var(--aic-input-fg)'] as $declaration) {
            $this->assertStringContainsString($declaration, $shared[1]);
        }

        // placeholder 와 select option 도 명시한다(다크에서 흰 글씨 on 흰 배경이 되던 부분).
        $this->assertMatchesRegularExpression('/\.aic-textarea::placeholder\s*\{[^}]*color:\s*var\(--aic-placeholder\)/', $this->css);
        $this->assertMatchesRegularExpression('/\.aic-select option\s*\{[^}]*background:\s*var\(--aic-input-bg\)[^}]*color:\s*var\(--aic-input-fg\)/', $this->css);

        // 네이티브 컨트롤(스크롤바·select 목록)이 다크에서 어둡게 그려지도록 color-scheme 을 두 모드에서 지정한다.
        $this->assertMatchesRegularExpression('/\.dark\s*\{[^}]*color-scheme:\s*dark/', $this->css);
        $this->assertMatchesRegularExpression('/:root\s*\{[^}]*color-scheme:\s*light/', $this->css);
    }

    public function test_widget_view_has_no_inline_style_block_and_loads_the_external_css(): void
    {
        // Blade 주석({{-- --}})은 설명 글이라 제외하고 본문만 본다.
        $strip = fn (string $file) => preg_replace('/\{\{--.*?--\}\}/s', '', (string) file_get_contents(resource_path($file))) ?? '';
        $view = $strip('views/livewire/ai-chat-widget.blade.php');

        $this->assertDoesNotMatchRegularExpression('/<style\b/i', $view, '위젯 blade 에 <style> 이 남아 있습니다');
        $this->assertDoesNotMatchRegularExpression('/style="[^"]*"/i', $view, '위젯 blade 에 인라인 style 속성이 있습니다');
        $this->assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{6}\b/', $view, '위젯 blade 에 색 리터럴이 있습니다');

        $card = $strip('views/components/ai/proposal-card.blade.php');
        $this->assertDoesNotMatchRegularExpression('/<style\b/i', $card);
        $this->assertDoesNotMatchRegularExpression('/style="[^"]*"/i', $card);
    }

    public function test_reduced_motion_and_small_screens_are_handled(): void
    {
        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $this->css);
        $this->assertStringContainsString('animation: none', $this->css);
        $this->assertStringContainsString('@media (max-width: 480px)', $this->css);
        // 대상 크기: 칩·버튼은 최소 높이 32px(WCAG 2.2 AA 2.5.8 의 24px 이상).
        foreach (['.aic-chip', '.aic-btn', '.aic-headbtn', '.aic-convdel', '.aic-send'] as $selector) {
            $this->assertMatchesRegularExpression('/'.preg_quote($selector, '/').'\s*\{[^}]*(min-height|min-width):\s*(2[4-9]|[3-9]\d)px/s', $this->css, "{$selector} 의 최소 크기");
        }
    }
}
