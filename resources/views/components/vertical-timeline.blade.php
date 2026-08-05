{{--
    세로 타임라인 — corporate/hospital 디자인 레퍼런스의 "연혁" 공통 DNA를 반영한 좌측정렬
    단일선 타임라인(연도 bold + 점 커넥터 + 하위 항목 리스트, kaomc/daejeon-beauty 레퍼런스의
    "단일 세로선" 컨벤션 참고). sub-header.blade.php와 동일한 @props + 한글 doc-comment 컨벤션.

    ⚠️ 주의(구운 HTML 드리프트): 이 마크업(태그 구조/클래스명)을 바꾸면 이미 시딩된 Page::content
    (DummyContentSeeder의 'history' 페이지, content_type=editor로 저장된 순수 HTML 문자열)는
    자동으로 갱신되지 않는다 — 구조를 바꿨다면 `php artisan db:seed --class=DummyContentSeeder`로
    재시딩해야 한다. 클래스명을 바꾸면 HtmlSanitizerService의 Attr.AllowedClasses 화이트리스트도
    함께 갱신할 것 — 안 하면 sanitize 과정에서 클래스가 조용히 제거되어(에러 없이) 스타일만 깨진다.

    사용법:
        <x-vertical-timeline :items="[
            ['year' => '2024', 'events' => ['01월 — 법인 설립', '06월 — 서비스 정식 오픈']],
            ['year' => '2025', 'events' => ['03월 — 누적 이용자 10,000명 달성']],
        ]" />
--}}
@props(['items' => []])
<ul class="vertical-timeline">
    @foreach ($items as $item)
        <li class="vertical-timeline__item">
            <p class="vertical-timeline__year">{{ $item['year'] ?? '' }}</p>
            @if (! empty($item['events']))
                <ul class="vertical-timeline__list">
                    @foreach ($item['events'] as $event)
                        <li>{{ $event }}</li>
                    @endforeach
                </ul>
            @endif
        </li>
    @endforeach
</ul>
