<?php

namespace App\Filament\Concerns;

use Livewire\Attributes\On;

// AI 비서 위젯(App\Livewire\AiChatWidget)이 만든 텍스트/이미지를 "폼에 채우기"만 하는 용도.
// 여기엔 저장 로직이 전혀 없다 — 폼 상태만 바뀌고, 실제 DB 반영은 화면의 기존 저장 버튼을
// 관리자가 직접 눌러야만 일어난다(AI에게 쓰기 권한을 아예 주지 않는다는 설계의 핵심).
trait HasAiFormFill
{
    #[On('fill-form-field')]
    public function fillAiGeneratedField(string $field, ?string $value): void
    {
        // $this->form->fill()은 쓰지 않는다 — FileUpload 같은 컴포넌트는 fill() 과정에서 자체
        // hydrate 훅이 돌면서 일반 문자열 경로를 그대로 안 받아들이고 빈 배열로 만들어버린다
        // (Livewire 임시업로드 형식을 기대함). 여기는 검증도, 컴포넌트별 가공도 필요 없이 폼이
        // 바인딩된 원본 배열($data)에 값만 그대로 꽂아주면 된다 — 다음 렌더링에 바로 반영된다.
        data_set($this->data, $field, $value);
    }
}
