<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Filament\Concerns\CancelsToListPage;
use App\Filament\Concerns\HasAiFormFill;
use App\Filament\Resources\Posts\PostResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPost extends EditRecord
{
    use CancelsToListPage;
    use HasAiFormFill;

    protected static string $resource = PostResource::class;

    private array $pendingAttachments = [];

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = PostResource::mergePlainContent($data);

        // 임시저장으로 전환하는 경우, 저장하는 본인을 소유자로 지정한다(원래 작성자가 다른
        // 관리자/회원이었더라도) — 그래야 이 글을 지금 임시저장 처리한 본인이 계속 볼 수 있다.
        // getEloquentQuery()의 임시저장 스코프가 user_id 기준이라, 그대로 두면 저장한 관리자
        // 스스로도 다음 목록 조회에서 이 글을 못 보게 되는 자기잠금(self-lockout)이 생긴다.
        if ($data['is_draft'] ?? false) {
            $data['user_id'] = auth()->id();
        }

        $this->pendingAttachments = PostResource::extractAttachments($data);

        return $data;
    }

    protected function afterSave(): void
    {
        PostResource::syncAttachments($this->record, $this->pendingAttachments);
    }
}
