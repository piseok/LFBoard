<?php

namespace Tests\Feature;

use App\Services\HtmlSanitizerService;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

// corporate/hospital 디자인 레퍼런스(.claude/design/corporate·hospital/README.md) 공통 DNA를 반영한
// 신규 공용 컴포넌트 <x-org-chart-tree>/<x-vertical-timeline>. 이 두 컴포넌트는 blade 뷰에서 직접
// 쓰이기도 하지만, DummyContentSeeder는 이 마크업과 "같은 구조"를 Page::content(에디터 타입, 구운
// HTML)로 저장한다 — 그 구운 HTML은 FrontController::page()에서 HtmlSanitizerService::clean()을
// 반드시 통과하므로, 여기서는 (1) 컴포넌트가 기대한 구조로 렌더되는지 (2) 그 렌더 결과가 sanitizer를
// 통과해도 클래스가 살아남는지(=스타일이 깨지지 않는지)를 함께 검증한다.
class StructuralComponentsTest extends TestCase
{
    public function test_org_chart_tree_renders_ceo_departments_and_teams(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-org-chart-tree
                :ceo="'대표이사 홍길동'"
                :departments="[
                    ['name' => '경영지원본부', 'teams' => ['총무팀', '인사팀']],
                    ['name' => '사업본부', 'teams' => ['영업팀']],
                ]"
            />
            BLADE);

        $this->assertStringContainsString('org-chart-tree', $html);
        $this->assertStringContainsString('org-chart-tree__ceo', $html);
        $this->assertStringContainsString('대표이사 홍길동', $html);
        $this->assertStringContainsString('org-chart-tree__dept', $html);
        $this->assertStringContainsString('경영지원본부', $html);
        $this->assertStringContainsString('org-chart-tree__team', $html);
        $this->assertStringContainsString('총무팀', $html);
        $this->assertStringContainsString('영업팀', $html);
    }

    public function test_org_chart_tree_omits_connector_and_depts_block_when_no_departments_given(): void
    {
        $html = Blade::render('<x-org-chart-tree :ceo="\'대표이사\'" />');

        $this->assertStringContainsString('org-chart-tree__ceo', $html);
        $this->assertStringNotContainsString('org-chart-tree__connector', $html);
        $this->assertStringNotContainsString('org-chart-tree__depts', $html);
    }

    public function test_vertical_timeline_renders_years_in_given_order_with_events(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-vertical-timeline :items="[
                ['year' => '2024', 'events' => ['01월 — 법인 설립', '06월 — 서비스 정식 오픈']],
                ['year' => '2025', 'events' => ['03월 — 누적 이용자 10,000명 달성']],
            ]" />
            BLADE);

        $this->assertStringContainsString('vertical-timeline', $html);
        $this->assertStringContainsString('vertical-timeline__year', $html);
        $this->assertStringContainsString('2024', $html);
        $this->assertStringContainsString('법인 설립', $html);
        $this->assertTrue(strpos($html, '2024') < strpos($html, '2025'));
    }

    public function test_org_chart_tree_markup_survives_html_sanitizer_with_classes_intact(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-org-chart-tree
                :ceo="'대표이사'"
                :departments="[['name' => '경영지원본부', 'teams' => ['총무팀']]]"
            />
            BLADE);

        $cleaned = app(HtmlSanitizerService::class)->clean($html);

        foreach (['org-chart-tree', 'org-chart-tree__ceo', 'org-chart-tree__connector', 'org-chart-tree__depts', 'org-chart-tree__dept-group', 'org-chart-tree__dept', 'org-chart-tree__teams', 'org-chart-tree__team'] as $class) {
            $this->assertStringContainsString($class, $cleaned, "sanitizer stripped expected class: {$class}");
        }
        $this->assertStringContainsString('대표이사', $cleaned);
        $this->assertStringContainsString('총무팀', $cleaned);
    }

    public function test_vertical_timeline_markup_survives_html_sanitizer_with_classes_intact(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-vertical-timeline :items="[['year' => '2026', 'events' => ['신규 서비스 확장']]]" />
            BLADE);

        $cleaned = app(HtmlSanitizerService::class)->clean($html);

        foreach (['vertical-timeline', 'vertical-timeline__item', 'vertical-timeline__year', 'vertical-timeline__list'] as $class) {
            $this->assertStringContainsString($class, $cleaned, "sanitizer stripped expected class: {$class}");
        }
        $this->assertStringContainsString('2026', $cleaned);
    }

    public function test_sanitizer_still_strips_classes_outside_the_allowed_list(): void
    {
        $cleaned = app(HtmlSanitizerService::class)->clean('<div class="org-chart-tree evil-tracking-class">x</div>');

        $this->assertStringContainsString('org-chart-tree', $cleaned);
        $this->assertStringNotContainsString('evil-tracking-class', $cleaned);
    }

    public function test_sanitizer_still_strips_script_tags(): void
    {
        $cleaned = app(HtmlSanitizerService::class)->clean('<div class="org-chart-tree"><script>alert(1)</script>x</div>');

        $this->assertStringNotContainsString('<script', $cleaned);
    }
}
