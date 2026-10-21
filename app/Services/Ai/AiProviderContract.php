<?php

namespace App\Services\Ai;

interface AiProviderContract
{
    public function key(): string;

    public function label(): string;

    public function isConfigured(): bool;

    public function supportsImageGeneration(): bool;

    /**
     * 이 제공자/모델이 함수 호출(도구)을 지원하는가. false 면 AiChatService 가 도구 없이 일반 대화로 내려간다 -
     * 모델 교체로 도구 호출이 안 되더라도 기능 전체가 멈추지 않게 하는 능력 플래그.
     */
    public function supportsTools(): bool;

    /**
     * 기존 단순 대화(시스템 지시문·도구 없음). chatWithTools() 에 위임하며 텍스트가 비면 예외다.
     *
     * @param  array<int, array{role: string, content: string|null}>  $history  오래된 순서
     */
    public function chat(array $history): string;

    /**
     * 시스템 지시문과 도구 선언을 함께 보내 "한 번만" 호출한다(도구 결과를 되돌려 보내는 왕복 없음).
     * 응답의 모든 파트에서 텍스트와 함수 호출을 모아 돌려준다. 빈 후보·안전 차단·잘못된 도구 인자는
     * 재시도 없이 RuntimeException 이다(재시도는 곧 비용).
     *
     * @param  array<int, array{role: string, content: string|null}>  $history  오래된 순서
     * @param  array<int, AiToolDeclaration>  $tools  비어 있으면 도구 없이 호출
     */
    public function chatWithTools(string $systemInstruction, array $history, array $tools = []): AiChatResult;

    /**
     * @return string 생성된 이미지가 저장된 로컬 임시 파일 경로
     */
    public function generateImage(string $prompt): string;
}
