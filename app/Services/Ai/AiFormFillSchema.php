<?php

namespace App\Services\Ai;

use App\Support\AiText;
use App\Support\SafeMarkdown;

// "열려 있는 작성·수정 화면에 AI 답변을 채우기" 의 허용 필드 표(화면별) - 단일 출처.
// 위젯의 버튼, HasAiFormFill 의 서버 측 검증, 시스템 지시문의 "지금 열린 화면" 안내가 모두 이 표를 쓴다.
// 이 표에 없는 필드는 어떤 경로로도 채워지지 않는다(AiFormFillSchemaTest 가 각 필드가 실제 폼에 있는지 고정).
//
// 필드 type
//  - line: 한 줄 입력(제목·ALT·메타 등) - 개행·제어문자를 공백으로, max 글자로 자름
//  - text: 여러 줄 평문 칸(Textarea) - 마크다운 서식 기호를 걷어 내고 개행은 유지
//  - rich: 리치 에디터 칸 - 마크다운을 안전한 HTML 로 바꿔 채움(링크·이미지는 글자만)
// 저장하는 코드는 여기에도, 채우는 쪽(HasAiFormFill)에도 없다 - 값은 폼 상태에만 들어가고 저장은 사람이 누른다.
final class AiFormFillSchema
{
    /**
     * @var array<string, array{label: string, resource: string, paths: array<int, string>, fields: array<string, array{label: string, type: string, max?: int}>, image_field: ?string}>
     */
    public const SCREENS = [
        'posts' => [
            'label' => '게시글 작성/수정', 'resource' => 'posts', 'paths' => ['posts/create', 'posts/*/edit'], 'image_field' => null,
            'fields' => [
                'title' => ['label' => '제목', 'type' => 'line', 'max' => 255],
                'content' => ['label' => '본문(에디터형 게시판)', 'type' => 'rich'],
                'content_text' => ['label' => '본문(텍스트형 게시판)', 'type' => 'text', 'max' => 60000],
            ],
        ],
        'boards' => [
            'label' => '게시판 작성/수정', 'resource' => 'boards', 'paths' => ['boards/create', 'boards/*/edit'], 'image_field' => null,
            'fields' => [
                'name' => ['label' => '게시판명', 'type' => 'line', 'max' => 50],
                'description' => ['label' => '설명', 'type' => 'text', 'max' => 2000],
            ],
        ],
        'inquiries' => [
            'label' => '1:1 상담 답변', 'resource' => 'inquiries', 'paths' => ['inquiries/*/edit'], 'image_field' => null,
            'fields' => [
                'admin_reply' => ['label' => '답변 내용', 'type' => 'rich'],
            ],
        ],
        'popups' => [
            'label' => '팝업 작성/수정', 'resource' => 'popups', 'paths' => ['popups/create', 'popups/*/edit'], 'image_field' => 'image_path',
            'fields' => [
                'title' => ['label' => '제목', 'type' => 'line', 'max' => 100],
                'html_content' => ['label' => 'HTML 내용', 'type' => 'rich'],
            ],
        ],
        'banners' => [
            'label' => '배너 작성/수정', 'resource' => 'banners', 'paths' => ['banners/create', 'banners/*/edit'], 'image_field' => 'image_path',
            'fields' => [
                'title' => ['label' => '제목', 'type' => 'line', 'max' => 100],
                'alt_text' => ['label' => 'ALT 텍스트', 'type' => 'line', 'max' => 255],
                'link_url' => ['label' => '링크 URL', 'type' => 'line', 'max' => 255],
            ],
        ],
        'pages' => [
            'label' => '콘텐츠 페이지 작성/수정', 'resource' => 'pages', 'paths' => ['pages/create', 'pages/*/edit'], 'image_field' => 'og_image',
            'fields' => [
                'title' => ['label' => '제목', 'type' => 'line', 'max' => 255],
                'content' => ['label' => '본문', 'type' => 'rich'],
                'meta_title' => ['label' => '메타 타이틀', 'type' => 'line', 'max' => 255],
                'meta_description' => ['label' => '메타 설명', 'type' => 'line', 'max' => 255],
                'meta_keywords' => ['label' => '메타 키워드', 'type' => 'line', 'max' => 255],
            ],
        ],
    ];

    // AI 생성 이미지가 저장되는 위치 - 이미지 필드는 이 아래 경로만 받는다(임의 경로 주입 방지).
    public const IMAGE_PREFIX = 'uploads/ai_generated/';

    /**
     * 요청 경로(예: "admin/posts/create")로 현재 화면을 찾는다. 대상 밖 화면이면 null.
     *
     * @return array{screen: string, label: string, fields: array<string, array{label: string, type: string, max?: int}>, image_field: ?string}|null
     */
    public static function detect(?string $path): ?array
    {
        $path = trim((string) $path, '/');
        $prefix = trim((string) config('app.admin_path', 'admin'), '/');

        foreach (self::SCREENS as $screen => $definition) {
            foreach ($definition['paths'] as $pattern) {
                if (fnmatch("{$prefix}/{$pattern}", $path)) {
                    return [
                        'screen' => $screen,
                        'label' => $definition['label'],
                        'fields' => $definition['fields'],
                        'image_field' => $definition['image_field'],
                    ];
                }
            }
        }

        return null;
    }

    // Filament 리소스(HasAiFormFill 를 쓰는 페이지의 $resource)에서 화면 키를 찾는다.
    public static function screenForResource(?string $resourceClass): ?string
    {
        if ($resourceClass === null) {
            return null;
        }

        foreach (self::SCREENS as $screen => $definition) {
            if ((AdminScreenMap::SCREENS[$definition['resource']]['class'] ?? null) === $resourceClass) {
                return $screen;
            }
        }

        return null;
    }

    public static function allowsField(string $screen, string $field): bool
    {
        $definition = self::SCREENS[$screen] ?? null;

        return $definition !== null
            && (array_key_exists($field, $definition['fields']) || $field === $definition['image_field']);
    }

    // 이미지 필드에 넣을 수 있는 경로인가 - AI 생성 이미지 폴더 아래의 상대 경로만.
    public static function isAllowedImagePath(?string $path): bool
    {
        return is_string($path)
            && str_starts_with($path, self::IMAGE_PREFIX)
            && ! str_contains($path, '..')
            && ! str_contains($path, '\\')
            && preg_match('#^[A-Za-z0-9_./\-]+\z#', $path) === 1; // `$` 는 끝의 개행 하나를 허용하므로 \z (리뷰 L5)
    }

    // AI 답변(마크다운 텍스트)을 해당 필드 형식에 맞는 값으로 바꾼다. 허용 필드가 아니면 null.
    public static function formatValue(string $screen, string $field, string $text): ?string
    {
        $definition = self::SCREENS[$screen]['fields'][$field] ?? null;

        if ($definition === null) {
            return null;
        }

        return match ($definition['type']) {
            'rich' => SafeMarkdown::editorHtml($text),
            'text' => AiText::multiLine(SafeMarkdown::plainText($text), $definition['max'] ?? 5000),
            default => $field === 'link_url'
                ? (AiText::httpUrl(self::firstLine($text), $definition['max'] ?? 255) ?? '')
                : AiText::singleLine(self::firstLine($text), $definition['max'] ?? 255),
        };
    }

    // 한 줄 칸(제목·ALT·메타 등)에는 답변 전체가 아니라 첫 줄만 넣는다 - 여러 문단 답변이 제목 칸에 통째로 들어가지 않게.
    // 목록 기호("- ")는 걷어 낸다.
    private static function firstLine(string $text): string
    {
        foreach (preg_split("/\n+/", SafeMarkdown::plainText($text)) ?: [] as $line) {
            $line = trim(preg_replace('/^(?:[-*]|\d+[.)])\s+/u', '', trim($line)) ?? '');

            if ($line !== '') {
                return $line;
            }
        }

        return '';
    }
}
