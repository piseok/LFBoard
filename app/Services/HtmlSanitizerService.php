<?php

namespace App\Services;

use HTMLPurifier;
use HTMLPurifier_Config;

class HtmlSanitizerService
{
    // 허용 태그: p, br, strong, em, u, s, h1~h6, ul, ol, li, a, img, table, tr, td, th, blockquote, pre, code, span, div
    // class 속성은 org-chart-tree/vertical-timeline 등 신규 구조적 컴포넌트(2026-08)가 에디터 콘텐츠에
    // 구운 HTML로 저장되기 때문에 필요해졌다 — 임의 class를 전부 허용하면 style 기반 UI 스푸핑 벡터가
    // 되므로, 아래 ALLOWED_CLASSES 화이트리스트(Attr.AllowedClasses)로 실제 사용하는 클래스명만 허용한다.
    // iframe은 오시는길 페이지 등에 관리자가 카카오맵/유튜브 embed 코드를 직접 붙여넣는 용도
    // (2026-09-22) — src는 아래 SAFE_IFRAME_REGEXP로 그 두 출처만 통과하도록 제한한다(임의
    // origin의 iframe을 허용하면 피싱/클릭재킹 벡터가 됨). allowfullscreen은 일부러 뺐다 —
    // HTMLPurifier의 Iframe 모듈은 HTML.Trusted가 켜져 있어야만 그 속성을 등록하는데(다른 모든
    // 안전장치를 다 꺼버리는 옵션이라 켤 수 없음), HTML.Allowed에 넣으면 "지원 안 하는 속성"으로
    // 매 요청마다 ErrorException이 터진다(2026-09-22 테스트로 실제 확인) — 속성이 빠지면 임베드된
    // 영상이 전체화면 버튼만 없어질 뿐 기능 자체는 정상 동작하므로 이 정도로 타협.
    private const ALLOWED_ELEMENTS = 'p[class],br,strong,em,u,s,h1,h2,h3,h4,h5,h6,ul[class],ol[class],li[class],a[href|target|rel|class],img[src|alt|width|height],table[class],tr,td,th,thead,tbody,blockquote,pre,code,span[class|style],div[class|style],iframe[src|width|height|frameborder]';

    // HTMLPurifier의 URI.SafeIframeRegexp — iframe src가 이 패턴에 안 맞으면(a[href]와 동일한
    // 방식으로) src 속성만 제거된다(태그 자체는 남지만 src 없는 iframe은 about:blank라 안전).
    // 유튜브 표준 embed 경로(/embed/...)와 카카오맵 도메인만 허용.
    private const SAFE_IFRAME_REGEXP = '%^https://(www\.youtube(-nocookie)?\.com/embed/|map\.kakao\.com/)%';

    // resources/views/components/org-chart-tree.blade.php, vertical-timeline.blade.php가 실제로
    // 렌더하는 클래스명과 정확히 일치해야 한다 — 두 파일 중 하나를 바꿨다면 여기도 함께 갱신할 것
    // (안 그러면 sanitize 과정에서 클래스가 조용히 제거되어 에러 없이 스타일만 깨진다).
    private const ALLOWED_CLASSES = [
        'org-chart-tree', 'org-chart-tree__ceo', 'org-chart-tree__connector',
        'org-chart-tree__depts', 'org-chart-tree__dept-group', 'org-chart-tree__dept',
        'org-chart-tree__teams', 'org-chart-tree__team',
        'vertical-timeline', 'vertical-timeline__item', 'vertical-timeline__year', 'vertical-timeline__list',
    ];

    public function clean(string $html): string
    {
        // storage/framework/cache/purifier는 빈 디렉토리라 git에 커밋되지 않는다 — 새로 배포하거나
        // 클론한 환경(CI 포함)에는 아예 존재하지 않으므로 여기서 직접 만들어준다.
        $cachePath = storage_path('framework/cache/purifier');
        if (! is_dir($cachePath)) {
            mkdir($cachePath, 0775, true);
        }

        $config = HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed', self::ALLOWED_ELEMENTS);
        $config->set('Attr.AllowedClasses', self::ALLOWED_CLASSES);
        // HTML.Allowed에 a[target]을 넣는 것만으로는 부족하다 — HTMLPurifier는 target 값 자체를
        // 별도로 Attr.AllowedFrameTargets(기본값 빈 배열)로 한 번 더 걸러서, 이게 없으면 에디터에서
        // "새창"으로 저장한 target="_blank"가 sanitize 단계에서 조용히 지워진다(에러 없이 그냥
        // 사라짐 — 2026-09-22 실제 버그로 확인: 에디터 링크 다이얼로그에서 새창을 선택해도 실제
        // 렌더링된 링크는 새창으로 안 열림). _blank만 허용한다.
        $config->set('Attr.AllowedFrameTargets', ['_blank']);
        // iframe도 target과 똑같은 함정이 있다 — HTML.Allowed에 iframe을 넣는 것만으론 부족하고
        // HTML.SafeIframe을 켠 뒤 URI.SafeIframeRegexp로 허용 출처를 명시해야 통과한다(둘 다 없으면
        // iframe 자체가 아예 안 씀). CSP(app/Http/Middleware/SecurityHeaders.php)의 frame-src도
        // 여기와 같은 두 출처로 맞춰뒀다 — 한쪽만 고치면 여전히 막힌다.
        $config->set('HTML.SafeIframe', true);
        $config->set('URI.SafeIframeRegexp', self::SAFE_IFRAME_REGEXP);
        // 동적 inline style(예: 진행률 바 width:NN%)이 필요한 컴포넌트가 생기기 전까지는 이 목록을
        // 넓히지 않는다 — org-chart-tree/vertical-timeline 둘 다 정적 마크업만 쓰므로 불필요. 향후
        // 그런 컴포넌트가 추가되면 이 CSS.AllowedProperties 게이트도 함께 검토해야 한다.
        $config->set('CSS.AllowedProperties', ['color', 'text-align', 'font-weight', 'font-style']);
        $config->set('AutoFormat.RemoveEmpty', false);
        $config->set('Cache.SerializerPath', $cachePath);

        $purifier = new HTMLPurifier($config);

        return $purifier->purify($html);
    }
}
