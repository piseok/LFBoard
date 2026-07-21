<?php

namespace App\Services\Ai;

use App\Services\SiteSettingService;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiProvider implements AiProviderContract
{
    private const CHAT_MODEL = 'gpt-4o-mini';

    private const IMAGE_MODEL = 'gpt-image-1';

    public function __construct(private readonly SiteSettingService $siteSettings) {}

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

    public function chat(array $history): string
    {
        $response = Http::withToken((string) $this->siteSettings->get('ai_openai_api_key'))
            ->timeout(30)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => self::CHAT_MODEL,
                'messages' => array_map(
                    fn (array $m) => ['role' => $m['role'], 'content' => (string) $m['content']],
                    $history
                ),
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('OpenAI 채팅 요청이 실패했습니다.');
        }

        $content = $response->json('choices.0.message.content');

        if (! is_string($content) || $content === '') {
            throw new RuntimeException('OpenAI 응답을 해석할 수 없습니다.');
        }

        return $content;
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
            throw new RuntimeException('OpenAI 이미지 생성 요청이 실패했습니다.');
        }

        $base64 = $response->json('data.0.b64_json');

        if (! is_string($base64) || $base64 === '') {
            throw new RuntimeException('OpenAI 이미지 응답을 해석할 수 없습니다.');
        }

        $path = tempnam(sys_get_temp_dir(), 'ai_img_').'.png';
        file_put_contents($path, base64_decode($base64));

        return $path;
    }
}
