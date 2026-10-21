<?php

namespace App\Services\Ai;

use RuntimeException;

// 개발용 가짜 제공자(local 환경 전용) - 유료 API 를 부르지 않고도 도구 호출·행동 카드·한도·잔여량·마크다운
// 흐름 전체를 브라우저로 확인하기 위한 것이다. AiChatService::providers() 가 app()->environment('local') 일 때만
// 목록에 넣는다(운영/시험에서는 나오지 않음). 응답은 결정적이다(같은 입력 → 같은 출력):
//
//   메시지에 "오류"      → 제공자 오류 흉내(예외)
//   "차단"               → 정책상 차단 흉내(채팅 SAFETY, 이미지 프롬프트에 "차단"이 있으면 IMAGE_RECITATION) - 이유별 안내·한도 미소모 확인용
//   "마크다운"/"서식"    → 마크다운 렌더링 시험용 답(굵게·목록·코드·링크·이미지·스크립트 태그 포함)
//   "팝업"               → propose_popup 호출 + 짧은 안내 문장(이미지 프롬프트 포함 → "이미지 만들고 열기" 버튼)
//   "게시글"/"글 써"      → 본문 예시 답 + propose_post 호출(채우기 버튼 시험용 본문 포함)
//   "페이지"             → propose_page 호출
//   "배너"               → propose_banner 호출
//   "열어"/"이동"/"어디"  → open_admin_page 호출(키워드로 화면 선택, 기본 posts)
//   그 외                → 고정 답
//
// 도구 선언이 비어 있으면(사이트 설정에서 도구 호출을 껐거나 제공자가 미지원) 도구 호출 없이 텍스트만 돌려준다.
class FakeChatProvider implements AiProviderContract
{
    use DelegatesPlainChat;

    public function key(): string
    {
        return 'fake';
    }

    public function label(): string
    {
        return '개발용 가짜(도구 호출 흉내)';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function supportsImageGeneration(): bool
    {
        return true;
    }

    public function supportsTools(): bool
    {
        return true;
    }

    public function chatWithTools(string $systemInstruction, array $history, array $tools = []): AiChatResult
    {
        $last = '';
        foreach (array_reverse($history) as $turn) {
            if (($turn['role'] ?? '') === 'user') {
                $last = (string) $turn['content'];
                break;
            }
        }

        $hasTools = $tools !== [];
        $names = array_map(fn (AiToolDeclaration $tool) => $tool->name, $tools);
        $can = fn (string $name) => $hasTools && in_array($name, $names, true);

        if (str_contains($last, '오류')) {
            throw new RuntimeException('개발용 가짜 제공자: 오류 흉내');
        }

        if (str_contains($last, '차단')) {
            throw AiBlockedException::forChat('SAFETY');
        }

        if (str_contains($last, '마크다운') || str_contains($last, '서식')) {
            return new AiChatResult(<<<'MD'
                ## 서식 시험

                **굵게**, *기울임*, `인라인 코드` 를 지원합니다.

                - 목록 항목 하나
                - 목록 항목 둘

                1. 번호 목록
                2. 두 번째

                [안전한 링크](https://example.com/guide) 는 새 창으로 열리고,
                [위험한 링크](javascript:alert(1)) 는 글자만 남습니다.

                ![추적 이미지](https://example.com/track.png?q=secret) 는 그림 없이 설명 글자만 보입니다.

                <script>alert('xss')</script> 는 글자 그대로 보입니다.

                ```
                코드 블록
                ```
                MD);
        }

        // "어디서 관리해?" 같은 길 안내는 만들기 요청("팝업 만들어줘")보다 먼저 판정한다.
        if ($can('open_admin_page') && (str_contains($last, '열어') || str_contains($last, '이동') || str_contains($last, '어디'))) {
            $screen = match (true) {
                str_contains($last, '게시판') => 'boards',
                str_contains($last, '문의') => 'inquiries',
                str_contains($last, '설정') => 'site_settings',
                str_contains($last, '배너') => 'banners',
                str_contains($last, '팝업') => 'popups',
                default => 'posts',
            };

            return new AiChatResult('해당 화면으로 가는 버튼을 준비했어요.', [
                new AiToolCall('open_admin_page', ['screen' => $screen]),
            ]);
        }

        if ($can('propose_popup') && str_contains($last, '팝업')) {
            return new AiChatResult('팝업 초안을 준비했어요. 아래 카드에서 작성 화면을 열어 내용을 확인하고 직접 저장해 주세요.', [
                new AiToolCall('propose_popup', [
                    'title' => '여름 휴가 안내',
                    'body' => "안녕하세요.\n8월 1일부터 8월 5일까지 여름 휴가로 휴무입니다.\n불편을 드려 죄송합니다.",
                    'start_offset_days' => 0,
                    'duration_days' => 14,
                    'image_prompt' => '여름 바다와 해변 파라솔, 밝고 따뜻한 일러스트, 글자 없음',
                    'image_alt' => '여름 휴가 안내 이미지',
                ]),
            ]);
        }

        if (str_contains($last, '게시글') || str_contains($last, '글 써')) {
            $body = "안녕하세요, 새로운 소식을 전합니다.\n\n**이번 달 주요 소식**\n- 신제품 출시\n- 고객 감사 이벤트\n\n많은 관심 부탁드립니다.";
            $calls = $can('propose_post')
                ? [new AiToolCall('propose_post', ['title' => '신제품 출시 안내', 'body' => $body])]
                : [];

            return new AiChatResult($body, $calls);
        }

        if ($can('propose_page') && str_contains($last, '페이지')) {
            return new AiChatResult('페이지 초안을 준비했어요. 아래 카드에서 작성 화면을 열어 확인해 주세요.', [
                new AiToolCall('propose_page', [
                    'title' => '회사 소개',
                    'slug' => 'about-us',
                    'body' => "저희 회사는 고객과 함께 성장합니다.\n\n연혁과 비전을 이곳에 적어 주세요.",
                    'meta_description' => '회사 소개 페이지입니다.',
                    'image_prompt' => '밝은 사무실 전경, 따뜻한 색감, 글자 없음',
                ]),
            ]);
        }

        if ($can('propose_banner') && str_contains($last, '배너')) {
            return new AiChatResult('배너 초안을 준비했어요. 아래 카드에서 작성 화면을 열어 확인해 주세요.', [
                new AiToolCall('propose_banner', [
                    'title' => '가을 신제품 배너',
                    'group_key' => 'main_top',
                    'alt_text' => '가을 신제품 배너 이미지',
                    'start_offset_days' => 0,
                    'duration_days' => 30,
                    'image_prompt' => '가을 단풍과 신제품 박스, 밝은 배경, 글자 없음',
                ]),
            ]);
        }

        return new AiChatResult('개발용 가짜 제공자의 고정 답변입니다. "팝업 만들어줘", "게시글 써줘", "페이지 만들어줘", "배너 만들어줘", "게시판 열어줘", "마크다운 보여줘", "오류 내줘" 를 입력해 보세요.');
    }

    // 단색 PNG - 프롬프트 글자 합으로 색을 정해 같은 프롬프트는 같은 색이다. GD 가 없으면 1x1 PNG.
    public function generateImage(string $prompt): string
    {
        if (str_contains($prompt, '차단')) {
            throw AiBlockedException::forImage('IMAGE_RECITATION');
        }

        $path = tempnam(sys_get_temp_dir(), 'ai_img_').'.png';

        if (function_exists('imagecreatetruecolor') && function_exists('imagepng')) {
            $hash = crc32($prompt);
            $image = imagecreatetruecolor(800, 450);
            $color = imagecolorallocate($image, 80 + ($hash & 0x7F), 80 + (($hash >> 8) & 0x7F), 80 + (($hash >> 16) & 0x7F));
            imagefill($image, 0, 0, $color);
            imagepng($image, $path);
            imagedestroy($image);

            return $path;
        }

        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));

        return $path;
    }
}
