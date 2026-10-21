<?php

namespace App\Services\Ai;

use App\Services\SiteSettingService;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class GeminiProvider implements AiProviderContract
{
    use DelegatesPlainChat;

    // 2026-10-21: 예전 모델이 Google 에서 종료돼 교체했다(공식 deprecations 페이지, 2026-10-20 갱신) -
    // gemini-2.0-flash(2026-06-01 종료)·imagen-3.0-generate-002(2025-11-10 종료). 이미지는 Imagen 의 :predict 가
    // 아니라 Gemini 이미지 모델의 generateContent(responseModalities)로 만든다. java-cms 의 GeminiProvider 와
    // 같은 모델·같은 호출 형식(실제 키로 채팅 텍스트·이미지 수신 확인됨).
    // 도구 호출(functionDeclarations)은 이 모델로 실측된 적이 없다 - 안 되면 사이트 설정 "도구 호출 사용"을
    // 꺼서 일반 대화로 내려갈 수 있다(AiChatService::toolsEnabled).
    private const CHAT_MODEL = 'gemini-3.8-flash';

    private const IMAGE_MODEL = 'gemini-nano-banana-2.1';

    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/';

    // 응답 이미지의 MIME 별 저장 확장자(UploadService 가 허용하는 이미지 확장자 안에서만).
    private const IMAGE_EXTENSIONS = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    // 후보가 이 사유로 끝나면 재시도하지 않고 오류로 처리한다(재시도는 곧 비용).
    private const BLOCKING_FINISH_REASONS = [
        'SAFETY', 'RECITATION', 'PROHIBITED_CONTENT', 'BLOCKLIST', 'SPII', 'MALFORMED_FUNCTION_CALL', 'OTHER',
    ];

    // "정책상 차단" 허용 목록 - 제공자가 안전·저작권·금지 콘텐츠 정책 때문에 결과를 만들지 않았고 관리자가 표현을 바꾸면 해결되는 사유만.
    // 이 코드일 때만 AiBlockedException 으로 이유별 안내를 만들고 일일 한도를 되돌려 준다(2026-10-21 실측: 단순한 아이콘 프롬프트가
    // HTTP 200 + 빈 content + finishReason IMAGE_RECITATION 으로 와서 "응답을 해석할 수 없습니다"라는 불친절한 오류가 됐다).
    //
    // 2026-10-21 결정(사용자): 정책 차단 환불에 횟수 상한은 두지 않는다(내부용 도구라 인당 하루 한도만 있으면 충분). 대신 환불 대상은
    // 이 명시 목록으로만 좁힌다 - 예전에는 `IMAGE_*` 접두 전부·"STOP 이 아닌 모든 사유"가 환불돼 IMAGE_OTHER·OTHER·MAX_TOKENS·NO_IMAGE 같은
    // 일반 코드(제공자가 연산을 했을 수 있음)까지 한도를 소모하지 않았다. 목록에 없는 코드는 일반 실패로 처리하고 한도를 소모한다.
    public const POLICY_REASONS = [
        'IMAGE_RECITATION', 'RECITATION',
        'IMAGE_SAFETY', 'SAFETY',
        'IMAGE_PROHIBITED_CONTENT', 'PROHIBITED_CONTENT',
        'BLOCKLIST', 'SPII',
    ];

    public function __construct(private readonly SiteSettingService $siteSettings) {}

    // 응답의 사유 코드(finishReason·blockReason)를 대문자 문자열로(문자열이 아니면 빈 문자열).
    private static function reasonCode(mixed $value): string
    {
        return is_string($value) ? strtoupper(trim($value)) : '';
    }

    // HTTP 오류 응답 → 상태 코드와 제공자의 error.status(예: NOT_FOUND·RESOURCE_EXHAUSTED)만 담은 예외. 원문 응답은 담지 않는다.
    private function httpFailure(string $message, Response $response): AiProviderException
    {
        return new AiProviderException($message, $response->status(), AiProviderException::cleanReason($response->json('error.status')));
    }

    public function key(): string
    {
        return 'gemini';
    }

    public function label(): string
    {
        return 'Google Gemini';
    }

    public function isConfigured(): bool
    {
        return filled($this->siteSettings->get('ai_gemini_api_key'));
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
        $apiKey = (string) $this->siteSettings->get('ai_gemini_api_key');

        $body = [
            // Gemini는 'assistant'가 아니라 'model' 롤을 사용한다.
            'contents' => array_map(
                fn (array $m) => [
                    'role' => $m['role'] === 'assistant' ? 'model' : 'user',
                    'parts' => [['text' => (string) $m['content']]],
                ],
                $history
            ),
        ];

        if ($systemInstruction !== '') {
            $body['systemInstruction'] = ['parts' => [['text' => $systemInstruction]]];
        }

        if ($tools !== []) {
            $body['tools'] = [['functionDeclarations' => array_map(
                fn (AiToolDeclaration $tool) => [
                    'name' => $tool->name,
                    'description' => $tool->description,
                    'parameters' => $tool->parameters,
                ],
                array_values($tools)
            )]];
            $body['toolConfig'] = ['functionCallingConfig' => ['mode' => 'AUTO']];
        }

        // API 키는 URL 쿼리(?key=)가 아니라 x-goog-api-key 헤더로 보낸다 — 쿼리에 넣으면 프록시·접근 로그·예외 메시지에 키가 남는다.
        $response = Http::withHeaders(['x-goog-api-key' => $apiKey])->timeout(30)
            ->post(self::ENDPOINT.self::CHAT_MODEL.':generateContent', $body);

        if (! $response->successful()) {
            throw $this->httpFailure('Gemini 채팅 요청이 실패했습니다.', $response);
        }

        // 프롬프트 자체가 차단되면 candidates 가 없다 - 허용 목록의 정책 사유면 재시도 없이 차단 안내, 그 밖의 사유는 일반 실패(한도 소모).
        $blockReason = self::reasonCode($response->json('promptFeedback.blockReason'));

        if ($blockReason !== '') {
            if (in_array($blockReason, self::POLICY_REASONS, true)) {
                throw AiBlockedException::forChat($blockReason);
            }

            throw new AiProviderException('Gemini 요청이 차단되었습니다.', $response->status(), AiProviderException::cleanReason($blockReason));
        }

        $finishReason = self::reasonCode($response->json('candidates.0.finishReason'));
        $text = [];
        $calls = [];

        // 모든 파트에서 텍스트와 함수 호출을 모은다(한 응답에 텍스트 파트와 functionCall 파트가 섞여 올 수 있다).
        foreach ((array) $response->json('candidates.0.content.parts', []) as $part) {
            if (! is_array($part)) {
                continue;
            }

            if (is_array($part['functionCall'] ?? null) && is_string($part['functionCall']['name'] ?? null)) {
                $args = $part['functionCall']['args'] ?? [];
                $calls[] = new AiToolCall($part['functionCall']['name'], is_array($args) ? $args : []);

                continue;
            }

            // thought 파트(모델의 사고 요약)는 사용자에게 보이는 답이 아니다.
            if (is_string($part['text'] ?? null) && ($part['thought'] ?? false) !== true) {
                $text[] = $part['text'];
            }
        }

        $joined = trim(implode("\n", $text));

        if ($joined === '' && $calls === []) {
            // 정책상 차단(안전·저작권 유사 등)은 이유별 안내로, 빈 후보 / MALFORMED_FUNCTION_CALL 은 일반 오류로 - 재시도하지 않는다.
            if (in_array($finishReason, self::POLICY_REASONS, true)) {
                throw AiBlockedException::forChat($finishReason);
            }

            throw new AiProviderException(in_array($finishReason, self::BLOCKING_FINISH_REASONS, true)
                ? 'Gemini 응답이 차단되었거나 도구 호출 형식이 잘못되었습니다.'
                : 'Gemini 응답을 해석할 수 없습니다.', $response->status(), AiProviderException::cleanReason($finishReason));
        }

        return new AiChatResult($joined, $calls);
    }

    public function generateImage(string $prompt): string
    {
        $apiKey = (string) $this->siteSettings->get('ai_gemini_api_key');

        $response = Http::withHeaders(['x-goog-api-key' => $apiKey])->timeout(60)
            ->post(self::ENDPOINT.self::IMAGE_MODEL.':generateContent', [
                'contents' => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => ['responseModalities' => ['TEXT', 'IMAGE']],
            ]);

        if (! $response->successful()) {
            throw $this->httpFailure('Gemini 이미지 생성 요청이 실패했습니다.', $response);
        }

        // 응답 파트 중 inlineData 가 있는 첫 파트가 이미지다(텍스트 파트가 앞에 올 수 있다).
        $inline = null;
        foreach ((array) $response->json('candidates.0.content.parts', []) as $part) {
            if (is_array($part) && is_string($part['inlineData']['data'] ?? null) && $part['inlineData']['data'] !== '') {
                $inline = $part['inlineData'];
                break;
            }
        }

        $bytes = $inline === null ? false : base64_decode($inline['data'], true);

        if ($bytes === false || $bytes === '') {
            // 한도 소모 기준(사용자 확정 2026-10-21, 같은 날 "명시 목록만 환불"로 좁힘):
            //  - 정책상 차단 = HTTP 200 + 이미지 없음 + finishReason(또는 프롬프트 차단 사유 promptFeedback.blockReason)이 POLICY_REASONS
            //    허용 목록의 정책 거절 코드(IMAGE_RECITATION·RECITATION·IMAGE_SAFETY·SAFETY·PROHIBITED_CONTENT·BLOCKLIST·SPII 등) → 이유별
            //    안내(사유 코드만, finishMessage·원문 응답·키는 노출 금지)를 보여 주고 **하루 이미지 한도를 소모하지 않는다**(AiChatService 가
            //    선기록한 사용 한 줄을 되돌린다).
            //  - 그 밖의 모든 경우는 **소모한다**: 5xx·타임아웃·연결 오류(위 successful() 검사), STOP 인데 이미지가 없는 응답, 그리고 목록에 없는
            //    코드(IMAGE_OTHER·OTHER·NO_IMAGE·MAX_TOKENS 등 - 정책 거절이라고 단정할 수 없고 제공자가 연산했을 수 있다).
            $blockReason = self::reasonCode($response->json('promptFeedback.blockReason'));
            $finishReason = self::reasonCode($response->json('candidates.0.finishReason'));

            foreach ([$blockReason, $finishReason] as $reason) {
                if (in_array($reason, self::POLICY_REASONS, true)) {
                    throw AiBlockedException::forImage($reason);
                }
            }

            throw new AiProviderException(
                'Gemini 이미지 응답을 해석할 수 없습니다.',
                $response->status(),
                AiProviderException::cleanReason($blockReason !== '' ? $blockReason : $finishReason),
            );
        }

        // 확장자는 응답 MIME 으로 정한다(Gemini 이미지 모델은 JPEG 도 돌려준다). AiChatService 가 이 확장자를 그대로 쓴다.
        $extension = self::IMAGE_EXTENSIONS[strtolower((string) ($inline['mimeType'] ?? ''))] ?? 'png';
        // tempnam() 이 만든 확장자 없는 빈 파일을 그대로 이름만 바꿔 쓴다(따로 새 파일을 만들면 빈 파일이 임시 폴더에 남는다).
        $base = tempnam(sys_get_temp_dir(), 'ai_img_');
        $path = $base.'.'.$extension;
        rename($base, $path);
        file_put_contents($path, $bytes);

        return $path;
    }
}
