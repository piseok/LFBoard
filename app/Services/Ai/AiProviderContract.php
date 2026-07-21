<?php

namespace App\Services\Ai;

interface AiProviderContract
{
    public function key(): string;

    public function label(): string;

    public function isConfigured(): bool;

    public function supportsImageGeneration(): bool;

    /**
     * @param  array<int, array{role: string, content: string|null}>  $history  오래된 순서
     */
    public function chat(array $history): string;

    /**
     * @return string 생성된 이미지가 저장된 로컬 임시 파일 경로
     */
    public function generateImage(string $prompt): string;
}
