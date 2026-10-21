<?php

namespace App\Services\Ai;

use App\Services\SiteSettingService;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiProvider implements AiProviderContract
{
    // 2026-10-21: 예전 모델이 Google 에서 종료돼 교체했다(공식 deprecations 페이지, 2026-10-20 갱신) -
    // gemini-2.0-flash(2026-06-01 종료)·imagen-3.0-generate-002(2025-11-10 종료). 이미지는 Imagen 의 :predict 가
    // 아니라 Gemini 이미지 모델의 generateContent(responseModalities)로 만든다. java-cms 의 GeminiProvider 와
    // 같은 모델·같은 호출 형식(실제 키로 채팅 텍스트·이미지 수신 확인됨).
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

    public function __construct(private readonly SiteSettingService $siteSettings) {}

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

    public function chat(array $history): string
    {
        $apiKey = (string) $this->siteSettings->get('ai_gemini_api_key');

        // API 키는 URL 쿼리(?key=)가 아니라 x-goog-api-key 헤더로 보낸다 — 쿼리에 넣으면 프록시·접근 로그·예외 메시지에 키가 남는다.
        $response = Http::withHeaders(['x-goog-api-key' => $apiKey])->timeout(30)
            ->post(self::ENDPOINT.self::CHAT_MODEL.':generateContent', [
                // Gemini는 'assistant'가 아니라 'model' 롤을 사용한다.
                'contents' => array_map(
                    fn (array $m) => [
                        'role' => $m['role'] === 'assistant' ? 'model' : 'user',
                        'parts' => [['text' => (string) $m['content']]],
                    ],
                    $history
                ),
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Gemini 채팅 요청이 실패했습니다.');
        }

        $text = $response->json('candidates.0.content.parts.0.text');

        if (! is_string($text) || $text === '') {
            throw new RuntimeException('Gemini 응답을 해석할 수 없습니다.');
        }

        return $text;
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
            throw new RuntimeException('Gemini 이미지 생성 요청이 실패했습니다.');
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
            throw new RuntimeException('Gemini 이미지 응답을 해석할 수 없습니다.');
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
