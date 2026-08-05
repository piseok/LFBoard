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
    private const ALLOWED_ELEMENTS = 'p[class],br,strong,em,u,s,h1,h2,h3,h4,h5,h6,ul[class],ol[class],li[class],a[href|target|rel|class],img[src|alt|width|height],table[class],tr,td,th,thead,tbody,blockquote,pre,code,span[class|style],div[class|style]';

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
