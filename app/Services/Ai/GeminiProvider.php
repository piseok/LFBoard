<?php

namespace App\Services\Ai;

use App\Services\SiteSettingService;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiProvider implements AiProviderContract
{
    private const CHAT_MODEL = 'gemini-2.0-flash';

    private const IMAGE_MODEL = 'imagen-3.0-generate-002';

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

        $response = Http::timeout(30)
            ->post("https://generativelanguage.googleapis.com/v1beta/models/".self::CHAT_MODEL.":generateContent?key={$apiKey}", [
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

        $response = Http::timeout(60)
            ->post("https://generativelanguage.googleapis.com/v1beta/models/".self::IMAGE_MODEL.":predict?key={$apiKey}", [
                'instances' => [['prompt' => $prompt]],
                'parameters' => ['sampleCount' => 1],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Gemini 이미지 생성 요청이 실패했습니다.');
        }

        $base64 = $response->json('predictions.0.bytesBase64Encoded');

        if (! is_string($base64) || $base64 === '') {
            throw new RuntimeException('Gemini 이미지 응답을 해석할 수 없습니다.');
        }

        $path = tempnam(sys_get_temp_dir(), 'ai_img_').'.png';
        file_put_contents($path, base64_decode($base64));

        return $path;
    }
}
