<?php

namespace App\Filament\Concerns;

use App\Services\Ai\AiFormFillSchema;
use Filament\Notifications\Notification;
use Livewire\Attributes\On;

// AI 비서 위젯(App\Livewire\AiChatWidget)이 만든 텍스트/이미지를 "폼에 채우기"만 하는 용도.
// 여기엔 저장 로직이 전혀 없다 — 폼 상태만 바뀌고, 실제 DB 반영은 화면의 기존 저장 버튼을
// 관리자가 직접 눌러야만 일어난다(AI에게 쓰기 권한을 아예 주지 않는다는 설계의 핵심).
// 이 파일에 저장 호출이 없다는 것은 AiFormFillSafetyTest 의 정적 시험이 고정한다.
//
// 보강(2026-10-21): 허용 필드 표(AiFormFillSchema)에 없는 필드·AI 생성 이미지 폴더 밖 경로는 무시한다 -
// 이 이벤트는 브라우저에서 임의로 보낼 수도 있으므로 서버 쪽에서도 같은 표로 검증한다.
trait HasAiFormFill
{
    #[On('fill-form-field')]
    public function fillAiGeneratedField(string $field, ?string $value): void
    {
        $screen = AiFormFillSchema::screenForResource(static::$resource ?? null);

        if ($screen === null || ! AiFormFillSchema::allowsField($screen, $field)) {
            return;
        }

        $definition = AiFormFillSchema::SCREENS[$screen];
        $isImage = $field === $definition['image_field'];

        if ($isImage && ! AiFormFillSchema::isAllowedImagePath($value)) {
            return;
        }

        // $this->form->fill()은 쓰지 않는다 — 전체 폼을 다시 hydrate 하면서 입력 중이던 다른 칸의 상태까지 흔든다.
        // 폼이 바인딩된 원본 배열($data)에 값만 그대로 꽂아주면 다음 렌더링에 바로 반영된다.
        // 파일 업로드 칸은 Filament 가 기존 파일을 [경로] 배열 상태로 들고 있으므로 같은 모양으로 넣는다
        // (문자열 그대로 넣으면 화면의 파일 미리보기가 그 값을 글자 배열로 읽는다).
        data_set($this->data, $field, $isImage ? [$value] : (string) $value);

        $label = $isImage ? '이미지' : ($definition['fields'][$field]['label'] ?? $field);

        Notification::make()
            ->title("{$label} 칸에 AI 내용을 채웠습니다")
            ->body('아직 저장되지 않았습니다. 내용을 확인한 뒤 화면의 저장 버튼을 직접 눌러야 반영됩니다.')
            ->success()
            ->send();

        // 채운 칸을 눈에 띄게 표시하는 브라우저 이벤트(public/js/ai-chat.js 가 듣는다).
        $this->dispatch('ai-field-filled', field: $field);
    }
}
