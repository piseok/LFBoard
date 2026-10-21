<?php

namespace App\Support;

use Illuminate\Support\HtmlString;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;

// AI 답변(마크다운)을 화면에 안전하게 그리는 렌더러. 모델 출력은 신뢰하지 않는다 - 허용 목록 방식이다.
//
//  - 원문 HTML 은 전부 이스케이프(글자 그대로 보임). <script>·on* 속성이 들어올 길이 없다.
//  - 링크는 http/https 만 살린다(rel="noopener noreferrer nofollow", 새 창, 도메인 표시). javascript:·data:·상대경로는 글자만 남긴다.
//  - **이미지는 그리지 않는다** - 외부 이미지 주소의 쿼리스트링으로 대화 내용이 유출되는 통로가 되기 때문이다. 설명 글자만 남긴다.
//  - 제목(#)은 제목 태그를 만들지 않고 굵은 문단으로 바꾼다(채팅 말풍선이 페이지 제목 구조를 흩뜨리지 않게).
//
// 두 가지 모드: chat(말풍선 표시용, 링크 유지)와 editor(리치 에디터 채우기용, 링크도 글자만 남김).
final class SafeMarkdown
{
    // 모델이 아주 긴 답을 줘도 렌더 비용이 무한정 늘지 않게.
    private const MAX_INPUT = 20000;

    /** @var array<string, MarkdownConverter> */
    private static array $converters = [];

    public static function chatHtml(?string $markdown): HtmlString
    {
        return new HtmlString(self::render((string) $markdown, allowLinks: true));
    }

    public static function editorHtml(?string $markdown): string
    {
        return self::render((string) $markdown, allowLinks: false);
    }

    // 서식 기호를 걷어 낸 평문(여러 줄 텍스트 칸·한 줄 칸 채우기용). 목록은 "- " 로 남긴다.
    public static function plainText(?string $markdown): string
    {
        $html = self::editorHtml($markdown);
        $html = preg_replace('#<li[^>]*>#i', "\n- ", $html) ?? '';
        $html = preg_replace('#<br\s*/?>\n?#i', "\n", $html) ?? ''; // 줄바꿈 뒤의 개행(soft_break 가 붙임)까지 한 번으로
        $html = preg_replace('#</(p|li|ul|ol|blockquote|pre|h[1-6])>|<hr\s*/?>#i', "\n", $html) ?? '';
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/\n{3,}/", "\n\n", str_replace("\r", '', $text)) ?? '';

        return trim($text);
    }

    private static function render(string $markdown, bool $allowLinks): string
    {
        $markdown = mb_substr($markdown, 0, self::MAX_INPUT);

        if (trim($markdown) === '') {
            return '';
        }

        return trim(self::converter($allowLinks)->convert($markdown)->getContent());
    }

    private static function converter(bool $allowLinks): MarkdownConverter
    {
        $key = $allowLinks ? 'chat' : 'editor';

        if (! isset(self::$converters[$key])) {
            $environment = new Environment([
                'html_input' => 'escape',
                'allow_unsafe_links' => false,
                'max_nesting_level' => 20,
                // 모델 답은 문단 안의 줄바꿈을 한 번의 개행으로만 주는 일이 많다 - 그대로 줄바꿈으로 보이게 한다.
                'renderer' => ['soft_break' => "<br />\n"],
            ]);
            $environment->addExtension(new CommonMarkCoreExtension);
            $environment->addRenderer(Image::class, self::imageRenderer(), 100);
            $environment->addRenderer(Link::class, self::linkRenderer($allowLinks), 100);
            $environment->addRenderer(Heading::class, self::headingRenderer(), 100);

            self::$converters[$key] = new MarkdownConverter($environment);
        }

        return self::$converters[$key];
    }

    private static function imageRenderer(): NodeRendererInterface
    {
        return new class implements NodeRendererInterface
        {
            public function render(Node $node, ChildNodeRendererInterface $childRenderer)
            {
                // 그림은 그리지 않고 대체 글자만 남긴다(주소는 버린다).
                return new HtmlElement('span', ['class' => 'aic-md-image'], '[그림: '.$childRenderer->renderNodes($node->children()).']');
            }
        };
    }

    private static function linkRenderer(bool $allowLinks): NodeRendererInterface
    {
        return new class($allowLinks) implements NodeRendererInterface
        {
            public function __construct(private readonly bool $allowLinks) {}

            public function render(Node $node, ChildNodeRendererInterface $childRenderer)
            {
                /** @var Link $node */
                $children = $childRenderer->renderNodes($node->children());
                $url = trim($node->getUrl());
                $host = preg_match('#^https?://#i', $url) ? parse_url($url, PHP_URL_HOST) : null;

                if (! $this->allowLinks || ! is_string($host) || $host === '' || preg_match('/[\s\x00-\x1f]/', $url)) {
                    return $children; // 글자만
                }

                return new HtmlElement('a', [
                    'href' => $url,
                    'target' => '_blank',
                    'rel' => 'noopener noreferrer nofollow',
                    'title' => $host,
                ], $children);
            }
        };
    }

    private static function headingRenderer(): NodeRendererInterface
    {
        return new class implements NodeRendererInterface
        {
            public function render(Node $node, ChildNodeRendererInterface $childRenderer)
            {
                return new HtmlElement('p', [], new HtmlElement('strong', [], $childRenderer->renderNodes($node->children())));
            }
        };
    }
}
