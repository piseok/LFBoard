<?php

namespace App\Services;

use HTMLPurifier;
use HTMLPurifier_Config;

class HtmlSanitizerService
{
    // 허용 태그: p, br, strong, em, u, s, h1~h6, ul, ol, li, a, img, table, tr, td, th, blockquote, pre, code, span, div
    private const ALLOWED_ELEMENTS = 'p,br,strong,em,u,s,h1,h2,h3,h4,h5,h6,ul,ol,li,a[href|target|rel],img[src|alt|width|height],table,tr,td,th,thead,tbody,blockquote,pre,code,span[style],div[style]';

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
        $config->set('CSS.AllowedProperties', ['color', 'text-align', 'font-weight', 'font-style']);
        $config->set('AutoFormat.RemoveEmpty', false);
        $config->set('Cache.SerializerPath', $cachePath);

        $purifier = new HTMLPurifier($config);

        return $purifier->purify($html);
    }
}
