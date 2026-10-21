<?php

namespace App\Filament\Concerns;

use App\Services\Ai\AiProposalService;
use Filament\Notifications\Notification;
use Livewire\Attributes\Locked;

// AI 비서의 행동 카드(제안)로 열린 "작성(Create) 화면"이 폼을 미리 채우는 공통 로직.
//
// 흐름: 카드 버튼 → 이 화면을 GET 으로 연다(?ai_proposal=<id>) → mount 때 제안의 소유자·종류·만료를 확인하고
// 폼 값만 채운다(비공개/비활성/임시저장 상태로). 저장은 하지 않는다 - 관리자가 화면의 저장 버튼을 눌러야 하고,
// 그때는 이 화면의 기존 권한 확인(canCreate)·검증·감사 로그가 그대로 적용된다. 쓰기 전용 AI 엔드포인트는 없다.
// 저장이 끝나면 markAiProposalUsed() 로 제안을 사용 처리하고 감사 로그에 제안 id 를 연결한다.
//
// 사용하는 페이지는 protected static string $aiProposalKind (AiChatProposal::KIND_*) 를 선언해야 한다.
trait HasAiProposalPrefill
{
    // 서버만 정한다(브라우저에서 바꿀 수 없음) - 다른 제안 id 로 저장 후 사용 처리를 하게 만들 수 없게.
    #[Locked]
    public ?int $aiProposalId = null;

    // 마운트 시 폼에 채운 칸 이름(안내 문구용).
    #[Locked]
    public array $aiPrefilledFields = [];

    // CreateRecord::fillForm() 을 덮어쓴다 - 원래 동작(기본값으로 폼 채우기) 뒤에 제안 값을 얹는다.
    protected function fillForm(): void
    {
        $this->callHook('beforeFill');

        $this->form->fill();
        $this->applyAiProposalPrefill();

        $this->callHook('afterFill');
    }

    protected function applyAiProposalPrefill(): void
    {
        $id = (int) request()->query('ai_proposal', 0);

        if ($id <= 0 || ! auth()->check()) {
            return;
        }

        $service = app(AiProposalService::class);
        $proposal = $service->resolveForPrefill(auth()->user(), $id, static::$aiProposalKind);

        if ($proposal === null) {
            Notification::make()
                ->title('AI 제안을 불러오지 못했습니다')
                ->body('내가 만든 제안이 아니거나, 이미 사용했거나, 1시간이 지나 만료되었습니다. AI 비서에게 다시 요청해주세요.')
                ->warning()
                ->send();

            return;
        }

        $data = $service->prefillData($proposal);

        // fill() 에는 기본값이 적용된 현재 원본 상태에 제안 값을 덮어쓴 전체를 넘긴다(일부만 넘기면 기본값이 빠진다).
        $this->form->fill(array_replace($this->form->getRawState(), $data));

        $this->aiProposalId = $proposal->id;
        $this->aiPrefilledFields = array_keys($data);

        Notification::make()
            ->title('AI 초안을 채웠습니다')
            ->body('아직 저장되지 않았습니다. 공개되지 않는 상태(비활성/임시저장)로 채워 두었으니 내용을 확인하고 필요하면 고친 뒤 직접 저장하세요.')
            ->success()
            ->send();
    }

    // 저장 직후 호출(각 Create 페이지의 afterCreate) - 제안을 사용 처리하고 감사 로그에 연결한다.
    protected function markAiProposalUsed(): void
    {
        if ($this->aiProposalId === null || ! auth()->check() || $this->record === null) {
            return;
        }

        app(AiProposalService::class)->markUsed(auth()->user(), $this->aiProposalId, static::$aiProposalKind, $this->record);
    }
}
