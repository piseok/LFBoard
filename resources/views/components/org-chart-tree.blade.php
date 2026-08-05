{{--
    조직도(트리형) — corporate/hospital 디자인 레퍼런스(.claude/design/corporate/README.md,
    hospital/README.md)의 공통 DNA를 반영한 3단 트리: 최상위 pill(대표) → dark-surface 박스(부서)
    → outline 그리드(팀). sub-header.blade.php와 동일하게 @props + 한글 doc-comment 컨벤션을 따른다.

    ⚠️ 주의(구운 HTML 드리프트): 이 마크업(태그 구조/클래스명)을 바꾸면 이미 시딩된 Page::content
    (DummyContentSeeder의 'organization' 페이지, content_type=editor로 저장된 순수 HTML 문자열)는
    자동으로 갱신되지 않는다 — 여기 구조를 바꿨다면 `php artisan db:seed --class=DummyContentSeeder`로
    재시딩해야 데모 콘텐츠도 함께 맞는다. 또한 클래스명을 바꾸면 HtmlSanitizerService의
    Attr.AllowedClasses 화이트리스트도 함께 갱신할 것 — 안 하면 sanitize 과정에서 클래스가 조용히
    제거되어(에러 없이) 스타일만 깨진다.

    사용법:
        <x-org-chart-tree
            :ceo="'대표이사 홍길동'"
            :departments="[
                ['name' => '경영지원본부', 'teams' => ['총무팀', '인사팀', '재무팀']],
                ['name' => '사업본부', 'teams' => ['영업팀', '마케팅팀']],
            ]"
        />
--}}
@props(['ceo' => null, 'departments' => []])
<div class="org-chart-tree">
    @if ($ceo)
        <div class="org-chart-tree__ceo">{{ $ceo }}</div>
        @if (! empty($departments))
            <div class="org-chart-tree__connector" aria-hidden="true"></div>
        @endif
    @endif
    @if (! empty($departments))
        <div class="org-chart-tree__depts">
            @foreach ($departments as $dept)
                <div class="org-chart-tree__dept-group">
                    <div class="org-chart-tree__dept">{{ $dept['name'] ?? '' }}</div>
                    @if (! empty($dept['teams']))
                        <ul class="org-chart-tree__teams">
                            @foreach ($dept['teams'] as $team)
                                <li class="org-chart-tree__team">{{ $team }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
