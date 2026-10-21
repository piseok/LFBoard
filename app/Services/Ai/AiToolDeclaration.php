<?php

namespace App\Services\Ai;

// 제공자 중립 "도구 선언" - 모델에게 "이런 함수를 호출해 제안할 수 있다"고 알려 주는 정의.
// parameters 는 JSON Schema 의 작은 부분집합(type/description/properties/required/enum/items)만 쓴다 -
// Gemini(OpenAPI 부분집합)와 OpenAI(JSON Schema)가 둘 다 받아들이는 공통분모다.
final class AiToolDeclaration
{
    /**
     * @param  array<string, mixed>  $parameters  type=object 인 JSON Schema
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly array $parameters,
    ) {}
}
