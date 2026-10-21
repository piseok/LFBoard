<?php

namespace App\Services\Ai;

use App\Services\SiteSettingService;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class OpenAiProvider implements AiProviderContract
{
    use DelegatesPlainChat;

    // 이 모델 이름들의 노후 여부는 아직 확인하지 못했다(Gemini 는 2026-10-21 에 종료 모델을 교체함) -
    // 실제 키를 넣고 확인할 때 같이 점검할 것(HANDOFF "아직 안 한 것").
    private const CHAT_MODEL = 'gpt-4o-mini';

    private const IMAGE_MODEL = 'gpt-image-1';

    public function __construct(private readonly SiteSettingService $siteSettings) {}

    // HTTP 오류 응답 → 상태 코드와 제공자의 error.code(예: model_not_found·invalid_api_key)만 담은 예외. 원문 응답은 담지 않는다.
    private function httpFailure(string $message, Response $response): AiProviderException
    {
        return new AiProviderException($message, $response->status(), AiProviderException::cleanReason($response->json('error.code')));
    }

    public function key(): string
    {
        return 'openai';
    }

    public function label(): string
    {
        return 'OpenAI';
    }

    public function isConfigured(): bool
    {
        return filled($this->siteSettings->get('ai_openai_api_key'));
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
        $messages = [];

        if ($systemInstruction !== '') {
            $messages[] = ['role' => 'system', 'content' => $systemInstruction];
        }

        foreach ($history as $m) {
            $messages[] = ['role' => $m['role'], 'content' => (string) $m['content']];
        }

        $body = ['model' => self::CHAT_MODEL, 'messages' => $messages];

        if ($tools !== []) {
            $body['tools'] = array_map(
                fn (AiToolDeclaration $tool) => [
                    'type' => 'function',
                    'function' => [
                        'name' => $tool->name,
                        'description' => $tool->description,
                        'parameters' => $tool->parameters,
                    ],
                ],
                array_values($tools)
            );
            $body['tool_choice'] = 'auto';
            // 한 번에 함수 하나만 - 병렬 호출을 끄면 응답이 단순해지고 카드 폭주가 줄어든다(OpenAI 는 tools 가 있을 때만 허용).
            $body['parallel_tool_calls'] = false;
        }

        $response = Http::withToken((string) $this->siteSettings->get('ai_openai_api_key'))
            ->timeout(30)
            ->post('https://api.openai.com/v1/chat/completions', $body);

        if (! $response->successful()) {
            throw $this->httpFailure('OpenAI 채팅 요청이 실패했습니다.', $response);
        }

        $finishReason = (string) $response->json('choices.0.finish_reason', '');
        $content = $response->json('choices.0.message.content');
        $calls = [];

        foreach ((array) $response->json('choices.0.message.tool_calls', []) as $call) {
            $name = $call['function']['name'] ?? null;

            if (! is_string($name) || $name === '') {
                continue;
            }

            // arguments 는 객체가 아니라 JSON "문자열"이다 - 파싱에 실패하거나 응답이 길이 제한으로 잘렸으면
            // 재시도 없이 오류(재시도는 곧 비용).
            $arguments = $call['function']['arguments'] ?? '{}';
            $args = is_string($arguments) ? json_decode($arguments === '' ? '{}' : $arguments, true) : null;

            if ($finishReason === 'length' || ! is_array($args)) {
                throw new AiProviderException('OpenAI 도구 호출 인자를 해석할 수 없습니다.', $response->status(), AiProviderException::cleanReason($finishReason));
            }

            $calls[] = new AiToolCall($name, $args);
        }

        $text = is_string($content) ? trim($content) : '';

        if ($text === '' && $calls === []) {
            // 콘텐츠 필터에 걸린 응답은 정책상 차단 안내로(재시도 없음, 한도 미소모).
            if ($finishReason === 'content_filter') {
                throw AiBlockedException::forChat('CONTENT_FILTER');
            }

            throw new AiProviderException('OpenAI 응답을 해석할 수 없습니다.', $response->status(), AiProviderException::cleanReason($finishReason));
        }

        return new AiChatResult($text, $calls);
    }

    public function generateImage(string $prompt): string
    {
        $response = Http::withToken((string) $this->siteSettings->get('ai_openai_api_key'))
            ->timeout(60)
            ->post('https://api.openai.com/v1/images/generations', [
                'model' => self::IMAGE_MODEL,
                'prompt' => $prompt,
                'size' => '1024x1024',
                'n' => 1,
            ]);

        if (! $response->successful()) {
            // 정책 위반(400 content_policy_violation / moderation_blocked)은 이유별 안내로 - 표현을 바꾸면 해결된다(한도 미소모).
            $errorCode = strtolower((string) $response->json('error.code', ''));

            if (in_array($errorCode, ['content_policy_violation', 'moderation_blocked'], true)) {
                throw AiBlockedException::forImage($errorCode);
            }

            throw $this->httpFailure('OpenAI 이미지 생성 요청이 실패했습니다.', $response);
        }

        $base64 = $response->json('data.0.b64_json');

        if (! is_string($base64) || $base64 === '') {
            throw new AiProviderException('OpenAI 이미지 응답을 해석할 수 없습니다.', $response->status());
        }

        $path = tempnam(sys_get_temp_dir(), 'ai_img_').'.png';
        file_put_contents($path, base64_decode($base64));

        return $path;
    }
}
